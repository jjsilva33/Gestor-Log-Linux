<?php
// Em vez de depender de um script no Mikrotik lendo a tabela de conexões a cada
// X segundos (frágil, e só descobre a tradução olhando reply-dst-address/port
// manualmente), aproveita que a regra de firewall "forward" com
// connection-state=new,snat já loga a tradução pronta na própria mensagem de
// syslog, algo como:
//   ..., proto UDP, 100.64.4.38:39104->172.217.113.4:443, NAT (100.64.4.38:39104->168.90.66.142:39104)->172.217.113.4:443, ...
// O trecho "NAT (privado:porta->publico:porta)" É o mapeamento que a busca
// judicial precisa. Esse log já chega confiável via rsyslog no SystemEvents
// (mesmo pipeline usado pro resto do painel) -- só falta extrair e indexar.
//
// Só pode ser executado via CLI (cron), nunca por uma requisição web direta.
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Acesso negado.');
}

require_once __DIR__ . '/../../Connections/site.php';

const TAMANHO_LOTE = 5000;
const MAX_LOTES_POR_EXECUCAO = 60; // até 300.000 linhas por execução, pra não ficar pra trás do volume real

const REGEX_NAT = '/proto\s+(TCP|UDP)(?:\s*\([^)]*\))?,\s*[\d.]+:\d+->[\d.]+:\d+,\s*NAT\s*\((\d{1,3}(?:\.\d{1,3}){3}):(\d+)->(\d{1,3}(?:\.\d{1,3}){3}):(\d+)\)->/i';

$ultimo_id = (int) ($pdo->query('SELECT ultimo_system_events_id FROM nat_logs_importacao_estado WHERE id = 1')->fetchColumn() ?: 0);

$stmt_buscar = $pdo->prepare(
    "SELECT ID, ReceivedAt, Message FROM SystemEvents
     WHERE ID > :ultimo_id AND Message LIKE '%NAT (%'
     ORDER BY ID ASC LIMIT " . TAMANHO_LOTE
);
$stmt_atualizar_estado = $pdo->prepare('UPDATE nat_logs_importacao_estado SET ultimo_system_events_id = :id WHERE id = 1');
$stmt_inserir = $pdo->prepare(
    'INSERT INTO nat_logs (ip_publico, porta_publica, ip_privado, porta_privada, protocolo, ts_amostra, ts_fim)
     VALUES (:ip_publico, :porta_publica, :ip_privado, :porta_privada, :protocolo, :ts_amostra, :ts_amostra)'
);

$total_importados = 0;
$total_ignorados = 0;
$lotes = 0;

do {
    $stmt_buscar->execute([':ultimo_id' => $ultimo_id]);
    $linhas = $stmt_buscar->fetchAll(PDO::FETCH_ASSOC);

    // Agrupa o lote inteiro numa única transação -- sem isso, cada um dos
    // milhares de INSERTs vira um commit (gravação em disco) separado, o
    // que é o maior motivo de lentidão aqui, não o volume de dados em si.
    $pdo->beginTransaction();

    foreach ($linhas as $linha) {
        $ultimo_id = max($ultimo_id, (int) $linha['ID']);

        if (!preg_match(REGEX_NAT, $linha['Message'], $m)) {
            $total_ignorados++;
            continue;
        }

        [, $protocolo, $ip_privado, $porta_privada, $ip_publico, $porta_publica] = $m;

        try {
            $stmt_inserir->execute([
                ':ip_publico' => substr($ip_publico, 0, 45),
                ':porta_publica' => (int) $porta_publica,
                ':ip_privado' => substr($ip_privado, 0, 45),
                ':porta_privada' => (int) $porta_privada,
                ':protocolo' => strtolower($protocolo),
                ':ts_amostra' => $linha['ReceivedAt'],
            ]);
            $total_importados++;
        } catch (PDOException $e) {
            $total_ignorados++;
        }
    }

    // Grava o progresso a cada lote (não só no final) -- se o script for
    // interrompido no meio, a próxima execução não reprocessa o que já foi feito.
    $stmt_atualizar_estado->execute([':id' => $ultimo_id]);
    $pdo->commit();
    $lotes++;
} while (count($linhas) === TAMANHO_LOTE && $lotes < MAX_LOTES_POR_EXECUCAO);

echo "Importados: $total_importados, ignorados: $total_ignorados, até o SystemEvents.ID $ultimo_id ($lotes lote(s)).\n";
