<?php
// Só pode ser executado via CLI (cron), nunca por uma requisição web direta.
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Acesso negado.');
}

require_once __DIR__ . '/../../../Connections/site.php';

$data_hora = date("YmdHis");
$comando = "MYSQL_PWD=" . escapeshellarg($password_site) . " mysqldump -h " . escapeshellarg($hostname_site) . " -u " . escapeshellarg($username_site) . " Syslog > /var/backups/gestorlog/diario/$data_hora.sql";
shell_exec($comando);
shell_exec("cd /var/backups/gestorlog/diario/; /bin/tar -czvf $data_hora.tar.gz $data_hora.sql; rm $data_hora.sql");
