<?php
// Endpoint que recebe, via POST, lotes de mapeamento NAT (IP privado:porta
// <-> IP público:porta) exportados por um script agendado no Mikrotik de
// borda/CGNAT. Não usa sessão de login — autentica só pelo token do
// cabeçalho X-Router-Token, pois quem chama é o roteador, não o navegador.

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'method_not_allowed']);
    exit;
}

$token_file = __DIR__ . '/token.php';
if (!file_exists($token_file)) {
    http_response_code(500);
    echo json_encode(['erro' => 'token_nao_configurado']);
    exit;
}
require $token_file;

$token_recebido = $_SERVER['HTTP_X_ROUTER_TOKEN'] ?? '';
if (!hash_equals(NAT_RECEIVER_TOKEN, $token_recebido)) {
    http_response_code(401);
    echo json_encode(['erro' => 'token_invalido']);
    exit;
}

$corpo = file_get_contents('php://input');
$itens = json_decode($corpo, true);

if (!is_array($itens)) {
    http_response_code(400);
    echo json_encode(['erro' => 'json_invalido']);
    exit;
}

require_once '../../Connections/site.php';

// O script do Mikrotik manda uma amostra a cada ~30s para toda conexão NAT
// ainda ativa. Em vez de inserir uma linha nova por amostra (o que faria a
// tabela crescer sem necessidade), tenta primeiro ESTENDER uma sessão já
// aberta com a mesma tupla IP:porta público <-> IP:porta privado nas
// últimas 24h; só insere linha nova quando é mesmo uma sessão diferente.
$sql_estender = "UPDATE `nat_logs`
    SET `ts_fim` = GREATEST(COALESCE(`ts_fim`, `ts_amostra`), :ts_novo)
    WHERE `ip_publico` = :ip_publico AND `porta_publica` = :porta_publica
      AND `ip_privado` = :ip_privado AND `porta_privada` = :porta_privada
      AND `protocolo` = :protocolo
      AND `ts_amostra` <= :ts_novo
      AND `ts_amostra` > DATE_SUB(:ts_novo, INTERVAL 24 HOUR)
    ORDER BY `ts_amostra` DESC
    LIMIT 1";
$stmt_estender = $pdo->prepare($sql_estender);

$sql_inserir = "INSERT INTO `nat_logs` (ip_publico, porta_publica, ip_privado, porta_privada, protocolo, ts_amostra, ts_fim)
    VALUES (:ip_publico, :porta_publica, :ip_privado, :porta_privada, :protocolo, :ts_amostra, :ts_amostra)";
$stmt_inserir = $pdo->prepare($sql_inserir);

$inseridos = 0;
$atualizados = 0;
$ignorados = 0;

foreach ($itens as $item) {
    if (
        !isset($item['ip_publico'], $item['porta_publica'], $item['ip_privado'], $item['porta_privada'], $item['protocolo'], $item['ts_inicio'])
    ) {
        $ignorados++;
        continue;
    }

    $ts_utc = converter_para_utc($item['ts_inicio']);
    if ($ts_utc === null) {
        $ignorados++;
        continue;
    }

    $parametros_comuns = [
        ':ip_publico' => substr($item['ip_publico'], 0, 45),
        ':porta_publica' => (int) $item['porta_publica'],
        ':ip_privado' => substr($item['ip_privado'], 0, 45),
        ':porta_privada' => (int) $item['porta_privada'],
        ':protocolo' => substr($item['protocolo'], 0, 10),
    ];

    try {
        $stmt_estender->execute($parametros_comuns + [':ts_novo' => $ts_utc]);
        if ($stmt_estender->rowCount() > 0) {
            $atualizados++;
            continue;
        }

        $stmt_inserir->execute($parametros_comuns + [':ts_amostra' => $ts_utc]);
        $inseridos++;
    } catch (PDOException $e) {
        $ignorados++;
    }
}

echo json_encode(['inseridos' => $inseridos, 'atualizados' => $atualizados, 'ignorados' => $ignorados]);

function converter_para_utc($ts_inicio)
{
    $dt = DateTime::createFromFormat(DateTime::ATOM, $ts_inicio);
    if ($dt === false) {
        return null;
    }
    $dt->setTimezone(new DateTimeZone('UTC'));
    return $dt->format('Y-m-d H:i:s');
}
