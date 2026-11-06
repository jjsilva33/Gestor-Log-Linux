<?php
// Rotina diária de retenção da tabela SystemEvents. Diferente do backup
// antigo (que truncava a tabela inteira a cada 2h), aqui só o que já
// passou de RETENCAO_DIAS é exportado para um arquivo e removido — o
// restante continua pesquisável pelo painel (Relatório, Dashboard,
// Estatística, Top Rank).
//
// Só pode ser executado via CLI (cron), nunca por uma requisição web direta.
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Acesso negado.');
}

const RETENCAO_DIAS = 365;
const TAMANHO_LOTE_EXCLUSAO = 5000;

require_once __DIR__ . '/../../../Connections/site.php';
require_once __DIR__ . '/rclone_upload.php';

$cutoff = date('Y-m-d H:i:s', strtotime('-' . RETENCAO_DIAS . ' days'));

$stmt_total = $pdo->prepare('SELECT COUNT(*) FROM SystemEvents WHERE ReceivedAt < :cutoff');
$stmt_total->execute([':cutoff' => $cutoff]);
$total_antigos = (int) $stmt_total->fetchColumn();

if ($total_antigos === 0) {
    echo "Nada a arquivar (nenhum registro antes de $cutoff).\n";
    exit;
}

// 1. Exporta os registros antigos antes de remover
$data_hora = date('YmdHis');
$pasta_retencao = '/var/backups/gestorlog/retencao';
if (!is_dir($pasta_retencao)) {
    mkdir($pasta_retencao, 0775, true);
}
$arquivo_sql = "$pasta_retencao/retencao_$data_hora.sql";

$comando_export = "MYSQL_PWD=" . escapeshellarg($password_site)
    . " mysqldump -h " . escapeshellarg($hostname_site)
    . " -u " . escapeshellarg($username_site)
    . " --where=" . escapeshellarg("ReceivedAt < '$cutoff'")
    . " " . escapeshellarg($database_site) . " SystemEvents > " . escapeshellarg($arquivo_sql);
shell_exec($comando_export);

if (!file_exists($arquivo_sql) || filesize($arquivo_sql) === 0) {
    echo "Falha ao exportar registros antigos, abortando exclusão.\n";
    exit(1);
}

shell_exec("cd " . escapeshellarg($pasta_retencao) . "; /bin/tar -czvf retencao_$data_hora.tar.gz retencao_$data_hora.sql; rm retencao_$data_hora.sql");
enviar_para_drive("$pasta_retencao/retencao_$data_hora.tar.gz", 'retencao');

// 2. Remove em lotes pra não travar a tabela com um DELETE gigante de uma vez
$stmt_delete = $pdo->prepare('DELETE FROM SystemEvents WHERE ReceivedAt < :cutoff LIMIT ' . TAMANHO_LOTE_EXCLUSAO);
$apagados_total = 0;
do {
    $stmt_delete->execute([':cutoff' => $cutoff]);
    $apagados = $stmt_delete->rowCount();
    $apagados_total += $apagados;
} while ($apagados > 0);

echo "Arquivados e removidos $apagados_total registros anteriores a $cutoff (backup: retencao_$data_hora.tar.gz).\n";
