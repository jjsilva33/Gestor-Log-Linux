<?php
// Restaura o banco a partir de um backup .tar.gz gerado em /var/backups/gestorlog/diario/.
// Ação destrutiva (substitui os dados atuais pelo conteúdo do backup) -- exige
// login de admin, igual às outras ações de manutenção (faltava antes).
if (!isset($_SESSION)) session_start();
$nivel_necessario = 5;
if (!isset($_SESSION['UsuarioID']) || ($_SESSION['UsuarioNivel'] > $nivel_necessario)) {
    session_destroy();
    header("Location: /site/login/index.php");
    exit;
}

require_once __DIR__ . '/../../../Connections/site.php';

$pasta_diario = '/var/backups/gestorlog/diario';
$arquivo_nome = $_REQUEST['arquivo'] ?? '';

// Só aceita o formato exato gerado pelo backup (AAAAMMDDHHMMSS.tar.gz) -- nunca
// usa o valor recebido cru num comando de shell (evita injeção de comando e
// path traversal, ex: "../../etc/passwd").
if (!preg_match('/^(\d{14})\.tar\.gz$/', $arquivo_nome, $m) || !file_exists("$pasta_diario/$arquivo_nome")) {
    echo "Erro, Contate o administrador 2";
    exit;
}

$data_hora_arquivo = $m[1];
$data_hora_agora = date('YmdHis');

$prefixo_credenciais = "MYSQL_PWD=" . escapeshellarg($password_site) . " ";
$host_usuario = "-h " . escapeshellarg($hostname_site) . " -u " . escapeshellarg($username_site);

// Dump de segurança do estado atual antes de sobrescrever, caso precise voltar atrás
shell_exec($prefixo_credenciais . "mysqldump $host_usuario Syslog > " . escapeshellarg("$pasta_diario/$data_hora_agora.sql"));
shell_exec("cd " . escapeshellarg($pasta_diario) . "; /bin/tar -czf " . escapeshellarg("$data_hora_agora.tar.gz") . " " . escapeshellarg("$data_hora_agora.sql") . "; rm " . escapeshellarg("$data_hora_agora.sql"));

// Descompacta e restaura. O dump do mysqldump já tem DROP TABLE/CREATE TABLE
// pra cada tabela, então não precisa de TRUNCATE manual antes.
shell_exec("cd " . escapeshellarg($pasta_diario) . "; /bin/tar -xzf " . escapeshellarg($arquivo_nome) . " -C " . escapeshellarg($pasta_diario));
$arquivo_sql_restaurado = "$pasta_diario/$data_hora_arquivo.sql";

if (!file_exists($arquivo_sql_restaurado)) {
    echo "Erro, Contate o administrador 3";
    exit;
}

shell_exec($prefixo_credenciais . "mysql $host_usuario Syslog < " . escapeshellarg($arquivo_sql_restaurado));
unlink($arquivo_sql_restaurado);

echo 'Backup restaurado com sucesso. <a href="/site/gestorserver/log/?pagina=status">Voltar</a>';
