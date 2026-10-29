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

$sql = "INSERT INTO `nat_logs` (ip_publico, porta_publica, ip_privado, porta_privada, protocolo, ts_amostra) VALUES (:ip_publico, :porta_publica, :ip_privado, :porta_privada, :protocolo, :ts_amostra)";
$stmt = $pdo->prepare($sql);

$inseridos = 0;
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

    try {
        $stmt->execute([
            ':ip_publico' => substr($item['ip_publico'], 0, 45),
            ':porta_publica' => (int) $item['porta_publica'],
            ':ip_privado' => substr($item['ip_privado'], 0, 45),
            ':porta_privada' => (int) $item['porta_privada'],
            ':protocolo' => substr($item['protocolo'], 0, 10),
            ':ts_amostra' => $ts_utc,
        ]);
        $inseridos++;
    } catch (PDOException $e) {
        $ignorados++;
    }
}

echo json_encode(['inseridos' => $inseridos, 'ignorados' => $ignorados]);

function converter_para_utc($ts_inicio)
{
    $dt = DateTime::createFromFormat(DateTime::ATOM, $ts_inicio);
    if ($dt === false) {
        return null;
    }
    $dt->setTimezone(new DateTimeZone('UTC'));
    return $dt->format('Y-m-d H:i:s');
}
