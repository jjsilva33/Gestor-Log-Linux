<?php
// Envia um arquivo de backup para o Google Drive via rclone, se o rclone
// estiver instalado e com o remote "gdrive" configurado no servidor. Se
// não estiver, não faz nada — o backup local já foi gerado antes desta
// função ser chamada, então a ausência do rclone nunca quebra o backup.
const RCLONE_REMOTE_DESTINO = 'gdrive:GestorLog-Backups';

function enviar_para_drive(string $arquivo_local, string $subpasta): void
{
    if (!file_exists($arquivo_local)) {
        return;
    }

    $tem_rclone = trim((string) shell_exec('command -v rclone 2>/dev/null')) !== '';
    if (!$tem_rclone) {
        return;
    }

    $remotes = (string) shell_exec('rclone listremotes 2>/dev/null');
    if (strpos($remotes, 'gdrive:') === false) {
        return;
    }

    $origem = escapeshellarg($arquivo_local);
    $destino = escapeshellarg(RCLONE_REMOTE_DESTINO . '/' . $subpasta . '/');
    shell_exec("rclone copy $origem $destino --log-file=/var/log/gestorlog-rclone.log 2>&1");
}
