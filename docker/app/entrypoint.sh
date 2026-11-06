#!/bin/sh
set -e

# Gera site/Connections/site.php a partir das variáveis de ambiente.
# Esse arquivo nunca é versionado (está no .gitignore) — em instalação
# tradicional é criado manualmente a partir do site.sample.php; aqui é
# gerado automaticamente toda vez que o container sobe.
cat > /var/www/html/site/Connections/site.php <<PHP
<?php
\$hostname_site = "${DB_HOST}";
\$database_site = "${DB_NAME}";
\$username_site = "${DB_USER}";
\$password_site = "${DB_PASSWORD}";

try {
    \$pdo = new PDO("mysql:host=\$hostname_site;dbname=\$database_site", \$username_site, \$password_site);
    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException \$e) {
    echo "Failed to connect to MySQL: " . \$e->getMessage();
    exit();
}
?>
PHP

# Gera o token do endpoint de log NAT (site/gestorserver/nat/receiver.php)
if [ -n "${NAT_RECEIVER_TOKEN}" ]; then
cat > /var/www/html/site/gestorserver/nat/token.php <<PHP
<?php
define('NAT_RECEIVER_TOKEN', '${NAT_RECEIVER_TOKEN}');
PHP
fi

chown www-data:www-data /var/www/html/site/Connections/site.php /var/www/html/site/gestorserver/nat/token.php 2>/dev/null || true

# Sobe o cron (backup agendado) em segundo plano
cron

exec "$@"
