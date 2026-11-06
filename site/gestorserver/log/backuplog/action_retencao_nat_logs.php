<?php
// Rotina diária de retenção da tabela nat_logs. Com o importador lendo direto
// do SystemEvents (uma conexão nova = uma linha), o volume aqui é grande --
// sem isso a tabela cresce sem limite. 365 dias é o mínimo exigido pelo
// art. 13 da Lei nº 12.965/2014 (Marco Civil da Internet) para registros de
// conexão -- ajuste RETENCAO_DIAS_NAT se precisar de outro período.
//
// Só pode ser executado via CLI (cron), nunca por uma requisição web direta.
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Acesso negado.');
}

const RETENCAO_DIAS_NAT = 365;
const TAMANHO_LOTE_EXCLUSAO = 5000;

require_once __DIR__ . '/../../../Connections/site.php';
require_once __DIR__ . '/google_drive.php';

$cutoff = date('Y-m-d H:i:s', strtotime('-' . RETENCAO_DIAS_NAT . ' days'));

$stmt_total = $pdo->prepare('SELECT COUNT(*) FROM nat_logs WHERE ts_amostra < :cutoff');
$stmt_total->execute([':cutoff' => $cutoff]);
$total_antigos = (int) $stmt_total->fetchColumn();

if ($total_antigos === 0) {
    echo "Nada a arquivar em nat_logs (nenhum registro antes de $cutoff).\n";
    exit;
}

$data_hora = date('YmdHis');
$pasta_retencao = '/var/backups/gestorlog/retencao';
if (!is_dir($pasta_retencao)) {
    mkdir($pasta_retencao, 0775, true);
}
$arquivo_sql = "$pasta_retencao/nat_logs_retencao_$data_hora.sql";

$comando_export = "MYSQL_PWD=" . escapeshellarg($password_site)
    . " mysqldump -h " . escapeshellarg($hostname_site)
    . " -u " . escapeshellarg($username_site)
    . " --where=" . escapeshellarg("ts_amostra < '$cutoff'")
    . " " . escapeshellarg($database_site) . " nat_logs > " . escapeshellarg($arquivo_sql);
shell_exec($comando_export);

if (!file_exists($arquivo_sql) || filesize($arquivo_sql) === 0) {
    echo "Falha ao exportar nat_logs antigos, abortando exclusão.\n";
    exit(1);
}

shell_exec("cd " . escapeshellarg($pasta_retencao) . "; /bin/tar -czvf nat_logs_retencao_$data_hora.tar.gz nat_logs_retencao_$data_hora.sql; rm nat_logs_retencao_$data_hora.sql");
enviar_para_drive("$pasta_retencao/nat_logs_retencao_$data_hora.tar.gz", 'nat_logs_retencao');

$stmt_delete = $pdo->prepare('DELETE FROM nat_logs WHERE ts_amostra < :cutoff LIMIT ' . TAMANHO_LOTE_EXCLUSAO);
$apagados_total = 0;
do {
    $stmt_delete->execute([':cutoff' => $cutoff]);
    $apagados = $stmt_delete->rowCount();
    $apagados_total += $apagados;
} while ($apagados > 0);

echo "Arquivados e removidos $apagados_total registros de nat_logs anteriores a $cutoff (backup: nat_logs_retencao_$data_hora.tar.gz).\n";
