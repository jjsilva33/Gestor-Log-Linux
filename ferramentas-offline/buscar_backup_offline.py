#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Busca judicial OFFLINE num backup baixado do Gestor Log.

Ferramenta separada do painel web -- não precisa do servidor, do Apache,
nem de MySQL/MariaDB instalado. Lê o .tar.gz do backup diário direto (ou
um .sql já extraído / uma pasta já extraída) e procura, dentro dele, o
mapeamento de NAT pra um IP público + porta num horário específico --
útil pra responder ofício quando os dados já saíram do servidor ao vivo
(backup antigo, ou pra mandar pro próprio cliente consultar no computador
dele).

Funciona com dois formatos de dado, dependendo de qual já existia no
backup no momento em que foi gerado:
  1. Tabela `nat_logs` (mapeamento já extraído) -- mais rápido e direto.
  2. Tabela `SystemEvents` (log bruto do syslog) -- procura a mensagem de
     firewall que já contém a tradução, tipo:
     "... proto UDP, 100.64.4.38:39104->172.217.113.4:443, NAT (100.64.4.38:39104->168.90.66.142:39104)->... "
Usa os dois se os dois existirem no dump, e combina os resultados.

Uso:
    python buscar_backup_offline.py --backup 20261106000001.tar.gz --ip 45.234.80.144 --porta 56626 --data-hora "06/11/2026 13:18:58"

    (--fuso -3 é o padrão, já assume horário de Brasília como nos ofícios;
    use --fuso 0 se a data/hora já estiver em UTC)

    Sem --data-hora, lista todas as ocorrências encontradas pra esse IP:porta,
    mais recentes primeiro.

Aceita em --backup:
  - um arquivo .tar.gz (lê o .sql de dentro dele direto, sem extrair)
  - um arquivo .sql já extraído
  - uma pasta já extraída (procura o primeiro .sql dentro dela)
"""
import argparse
import csv
import datetime as dt
import re
import sys
import tarfile
from pathlib import Path

REGEX_INSERT = re.compile(
    r"INSERT INTO `?(\w+)`?\s*(?:\(([^)]*)\)\s*)?VALUES\s*(.+?);(?:\r?\n|$)",
    re.IGNORECASE | re.DOTALL,
)

REGEX_CREATE_TABLE = re.compile(
    r"CREATE TABLE `?(\w+)`?\s*\((.*?)\n\)\s*(?:ENGINE|;)",
    re.IGNORECASE | re.DOTALL,
)
REGEX_COLUNA_NO_CREATE = re.compile(r"^\s*`(\w+)`", re.MULTILINE)

REGEX_NAT_NA_MENSAGEM = re.compile(
    r"proto\s+(TCP|UDP)(?:\s*\([^)]*\))?,\s*[\d.]+:\d+->[\d.]+:\d+,\s*NAT\s*\("
    r"(\d{1,3}(?:\.\d{1,3}){3}):(\d+)->(\d{1,3}(?:\.\d{1,3}){3}):(\d+)\)->",
    re.IGNORECASE,
)

TABELAS_RELEVANTES = {"nat_logs", "systemevents"}


def ler_conteudo_sql(caminho_backup: Path) -> str:
    if caminho_backup.is_dir():
        candidatos = sorted(caminho_backup.glob("*.sql"))
        if not candidatos:
            raise SystemExit(f"Nenhum .sql encontrado dentro da pasta {caminho_backup}")
        return candidatos[0].read_text(encoding="utf-8", errors="replace")

    nome = caminho_backup.name.lower()
    if nome.endswith(".tar.gz") or nome.endswith(".tgz"):
        with tarfile.open(caminho_backup, "r:gz") as tar:
            for membro in tar.getmembers():
                if membro.name.lower().endswith(".sql"):
                    f = tar.extractfile(membro)
                    if f:
                        return f.read().decode("utf-8", errors="replace")
        raise SystemExit(f"Nenhum .sql encontrado dentro de {caminho_backup}")

    return caminho_backup.read_text(encoding="utf-8", errors="replace")


def parse_valores_sql(bloco_values: str):
    """
    Faz o parse manual de uma lista de tuplas "(a,'b',c),(d,'e',f)" de um
    dump mysqldump, respeitando aspas/escapes e parênteses literais dentro
    de texto (ex: "(SYN)" dentro de uma mensagem de log) -- não dá pra usar
    split(',') direto por causa disso.
    """
    linhas = []
    tupla_atual = []
    campo_atual = ""
    dentro_aspas = False
    profundidade = 0
    i, n = 0, len(bloco_values)

    while i < n:
        c = bloco_values[i]

        if dentro_aspas:
            if c == "\\" and i + 1 < n:
                campo_atual += c + bloco_values[i + 1]
                i += 2
                continue
            if c == "'":
                dentro_aspas = False
                campo_atual += c
                i += 1
                continue
            campo_atual += c
            i += 1
            continue

        if c == "'":
            dentro_aspas = True
            campo_atual += c
        elif c == "(":
            profundidade += 1
            if profundidade == 1:
                tupla_atual = []
                campo_atual = ""
            else:
                campo_atual += c
        elif c == ")":
            profundidade -= 1
            if profundidade == 0:
                tupla_atual.append(campo_atual.strip())
                linhas.append(tupla_atual)
                campo_atual = ""
            else:
                campo_atual += c
        elif c == "," and profundidade == 1:
            tupla_atual.append(campo_atual.strip())
            campo_atual = ""
        else:
            campo_atual += c
        i += 1

    return linhas


def desaspar(valor: str):
    valor = valor.strip()
    if valor.upper() == "NULL":
        return None
    if len(valor) >= 2 and valor.startswith("'") and valor.endswith("'"):
        return valor[1:-1].replace("\\'", "'").replace('\\"', '"').replace("\\\\", "\\")
    return valor


def extrair_colunas_create_table(sql_texto: str):
    """
    mysqldump normalmente NÃO inclui a lista de colunas no INSERT
    ("INSERT INTO `tabela` VALUES (...)", sem "(col1, col2, ...)") --
    nesse caso a ordem das colunas só dá pra saber olhando o CREATE TABLE
    que vem antes no mesmo dump.
    """
    colunas_por_tabela = {}
    for m in REGEX_CREATE_TABLE.finditer(sql_texto):
        nome_tabela = m.group(1).strip("`").lower()
        if nome_tabela not in TABELAS_RELEVANTES:
            continue
        colunas = REGEX_COLUNA_NO_CREATE.findall(m.group(2))
        if colunas:
            colunas_por_tabela[nome_tabela] = colunas
    return colunas_por_tabela


def carregar_tabelas(sql_texto: str):
    """Retorna {nome_tabela_minusculo: [ {coluna: valor}, ... ]} só das tabelas relevantes."""
    colunas_do_create = extrair_colunas_create_table(sql_texto)
    tabelas = {}

    for m in REGEX_INSERT.finditer(sql_texto):
        nome_tabela = m.group(1).strip("`").lower()
        if nome_tabela not in TABELAS_RELEVANTES:
            continue

        if m.group(2):
            colunas = [c.strip(" `") for c in m.group(2).split(",")]
        else:
            colunas = colunas_do_create.get(nome_tabela)
            if not colunas:
                continue  # sem lista de colunas no INSERT nem CREATE TABLE no dump -- não dá pra mapear com segurança

        linhas = tabelas.setdefault(nome_tabela, [])
        for tupla in parse_valores_sql(m.group(3)):
            if len(tupla) != len(colunas):
                continue
            linhas.append({col: desaspar(val) for col, val in zip(colunas, tupla)})
    return tabelas


def buscar_nat_logs(linhas, ip_publico, porta_publica, ts_utc):
    resultados = []
    for linha in linhas:
        if linha.get("ip_publico") != ip_publico or str(linha.get("porta_publica")) != str(porta_publica):
            continue
        ts_amostra = linha.get("ts_amostra")
        ts_fim = linha.get("ts_fim") or ts_amostra
        cobre = (ts_utc is None) or (ts_amostra is not None and ts_amostra <= ts_utc <= ts_fim)
        resultados.append({
            "origem": "nat_logs",
            "ip_privado": linha.get("ip_privado"),
            "porta_privada": linha.get("porta_privada"),
            "protocolo": linha.get("protocolo"),
            "inicio": ts_amostra,
            "fim": ts_fim,
            "cobre_exato": cobre,
        })
    return resultados


def buscar_systemevents(linhas, ip_publico, porta_publica, ts_utc, tolerancia_segundos):
    resultados = []
    for linha in linhas:
        mensagem = linha.get("Message") or linha.get("message") or ""
        if "NAT (" not in mensagem:
            continue
        m = REGEX_NAT_NA_MENSAGEM.search(mensagem)
        if not m:
            continue
        protocolo, ip_priv, porta_priv, ip_pub, porta_pub = m.groups()
        if ip_pub != ip_publico or porta_pub != str(porta_publica):
            continue

        received_at = linha.get("ReceivedAt") or linha.get("receivedat")
        delta_segundos = None
        cobre = True
        if ts_utc is not None and received_at:
            try:
                t_evento = dt.datetime.strptime(received_at, "%Y-%m-%d %H:%M:%S")
                t_alvo = dt.datetime.strptime(ts_utc, "%Y-%m-%d %H:%M:%S")
                delta_segundos = (t_evento - t_alvo).total_seconds()
                cobre = abs(delta_segundos) <= tolerancia_segundos
            except ValueError:
                pass

        resultados.append({
            "origem": "SystemEvents",
            "ip_privado": ip_priv,
            "porta_privada": porta_priv,
            "protocolo": protocolo.lower(),
            "inicio": received_at,
            "fim": received_at,
            "delta_segundos": delta_segundos,
            "cobre_exato": cobre,
        })
    return resultados


def converter_para_utc(data_hora_str: str, fuso_horas: int) -> str:
    formatos = ("%d/%m/%Y %H:%M:%S", "%d/%m/%Y %H:%M", "%Y-%m-%d %H:%M:%S", "%Y-%m-%d %H:%M")
    for fmt in formatos:
        try:
            t_local = dt.datetime.strptime(data_hora_str, fmt)
            t_utc = t_local - dt.timedelta(hours=fuso_horas)
            return t_utc.strftime("%Y-%m-%d %H:%M:%S")
        except ValueError:
            continue
    raise SystemExit(
        f"Não entendi o formato de --data-hora: '{data_hora_str}'. "
        "Use DD/MM/AAAA HH:MM:SS (ex: 06/11/2026 13:18:58)."
    )


def main():
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--backup", required=True, help="Caminho do .tar.gz, .sql ou pasta extraída do backup")
    parser.add_argument("--ip", required=True, help="IP público citado no ofício")
    parser.add_argument("--porta", required=True, type=int, help="Porta pública citada no ofício")
    parser.add_argument("--data-hora", help="Data/hora do ofício, ex: '06/11/2026 13:18:58' (sem isso, lista tudo)")
    parser.add_argument("--fuso", type=int, default=-3, help="Fuso horário de --data-hora em relação a UTC (padrão -3, Brasília)")
    parser.add_argument("--tolerancia-segundos", type=int, default=120, help="Janela de tolerância pra bater o horário com SystemEvents (padrão 120s)")
    parser.add_argument("--csv", help="Também salva os resultados nesse arquivo .csv")
    args = parser.parse_args()

    caminho_backup = Path(args.backup)
    if not caminho_backup.exists():
        raise SystemExit(f"Arquivo/pasta não encontrado: {caminho_backup}")

    ts_utc = converter_para_utc(args.data_hora, args.fuso) if args.data_hora else None

    print(f"Lendo {caminho_backup} ...")
    sql_texto = ler_conteudo_sql(caminho_backup)
    print(f"{len(sql_texto):,} caracteres lidos. Procurando tabelas nat_logs / SystemEvents...")

    tabelas = carregar_tabelas(sql_texto)
    print(f"Encontrado: nat_logs={len(tabelas.get('nat_logs', []))} linha(s), "
          f"SystemEvents={len(tabelas.get('systemevents', []))} linha(s).\n")

    resultados = []
    if "nat_logs" in tabelas:
        resultados += buscar_nat_logs(tabelas["nat_logs"], args.ip, args.porta, ts_utc)
    if "systemevents" in tabelas:
        resultados += buscar_systemevents(tabelas["systemevents"], args.ip, args.porta, ts_utc, args.tolerancia_segundos)

    if not resultados:
        print(f"Nenhuma ocorrencia de {args.ip}:{args.porta} encontrada neste backup.")
        sys.exit(1)

    resultados.sort(key=lambda r: (not r.get("cobre_exato", True), r.get("inicio") or ""))

    print(f"{'Origem':<12} {'IP privado':<16} {'Porta':<7} {'Proto':<6} {'Inicio (UTC)':<20} {'Fim (UTC)':<20} {'Cobre exato?'}")
    for r in resultados:
        print(f"{r['origem']:<12} {str(r['ip_privado']):<16} {str(r['porta_privada']):<7} "
              f"{str(r['protocolo']):<6} {str(r['inicio']):<20} {str(r['fim']):<20} "
              f"{'sim' if r.get('cobre_exato') else 'nao (mais proximo)'}")

    if args.csv:
        with open(args.csv, "w", newline="", encoding="utf-8") as f:
            campos = ["origem", "ip_privado", "porta_privada", "protocolo", "inicio", "fim", "cobre_exato"]
            writer = csv.DictWriter(f, fieldnames=campos, extrasaction="ignore")
            writer.writeheader()
            writer.writerows(resultados)
        print(f"\nResultados tambem salvos em {args.csv}")


if __name__ == "__main__":
    main()
