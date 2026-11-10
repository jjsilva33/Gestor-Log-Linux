# Busca judicial offline (backup baixado)

Ferramenta separada do painel web do Gestor Log — não precisa do servidor,
Apache, nem MySQL/MariaDB instalado. Só precisa de **Python 3** (nenhuma
biblioteca extra, `pip install` não é necessário).

Serve pra responder um ofício quando os dados já não estão mais no
servidor ao vivo (foram pro backup), ou pra mandar pro próprio cliente
consultar no computador dele, sem acesso ao servidor.

## Uso

```
python buscar_backup_offline.py --backup CAMINHO_DO_BACKUP --ip IP_PUBLICO --porta PORTA --data-hora "DD/MM/AAAA HH:MM:SS"
```

Exemplo, com os dados do ofício (IP 45.234.80.144, porta 56626, 06/11/2026
às 13:18:58 no horário de Brasília):

```
python buscar_backup_offline.py --backup "C:\Users\Jeff\Downloads\11-06-2026\var\backups\gestorlog\diario\20261106000001.tar.gz" --ip 45.234.80.144 --porta 56626 --data-hora "06/11/2026 13:18:58"
```

`--backup` aceita três formatos, o que já tiver em mãos:
- o `.tar.gz` do backup direto (lê o `.sql` de dentro sem precisar extrair manualmente)
- um `.sql` já extraído
- uma pasta já extraída (ele acha o `.sql` sozinho)

`--fuso` é `-3` por padrão (horário de Brasília, como vem nos ofícios) —
se a data/hora que você tiver já estiver em UTC, use `--fuso 0`.

Sem `--data-hora`, lista **todas** as ocorrências encontradas pra esse
IP:porta no backup, mais recentes primeiro (útil quando não se sabe o
horário exato).

Pra salvar o resultado num arquivo (pra anexar na resposta do ofício):

```
... --csv resultado.csv
```

## Como funciona

Procura dentro do backup por duas fontes, combinando as duas se as duas
existirem:

1. **Tabela `nat_logs`** (se o backup já for recente o suficiente pra ter
   essa tabela): mapeamento de NAT já pronto, IP/porta público → IP/porta
   privado.
2. **Tabela `SystemEvents`** (log bruto do syslog, sempre existe): procura
   direto na mensagem de firewall a tradução de NAT, que já vem pronta no
   formato `NAT (privado:porta->publico:porta)`.

Isso cobre tanto backups novos (depois que `nat_logs` passou a existir)
quanto backups mais antigos, que só têm o `SystemEvents`.
