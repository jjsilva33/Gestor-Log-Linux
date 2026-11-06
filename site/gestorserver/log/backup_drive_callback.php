<?php
require_once __DIR__ . '/backuplog/google_drive.php';

$mensagem = '';
$erro = '';

if (isset($_GET['error'])) {
    $erro = 'Autorização cancelada ou negada pelo Google.';
} elseif (isset($_GET['code'])) {
    $mensagem = drive_trocar_code_por_token($_GET['code'])
        ? 'Conectado ao Google Drive com sucesso!'
        : '';
    if ($mensagem === '') {
        $erro = 'Não foi possível concluir a conexão. Confirme o Client ID/Secret e tente novamente.';
    }
} else {
    $erro = 'Requisição inválida.';
}
?>
<div class="container">
    <div class="row clearfix">
        <div class="col-md-12 column">
            <span class="label label-default">Backup na nuvem (Google Drive)</span>
            <?php if ($mensagem): ?>
                <div class="notice-box" style="background: rgba(45, 212, 191, 0.12); border-color: rgba(45, 212, 191, 0.35); color:#e4e7ec; border:1px solid; border-radius:8px; padding:12px 14px; font-size:13px; margin-bottom:16px;"><?php echo htmlspecialchars($mensagem); ?></div>
            <?php else: ?>
                <div class="notice-box" style="background: rgba(220, 53, 69, 0.12); border-color: rgba(220, 53, 69, 0.35); color:#e4e7ec; border:1px solid; border-radius:8px; padding:12px 14px; font-size:13px; margin-bottom:16px;"><?php echo htmlspecialchars($erro); ?></div>
            <?php endif; ?>
            <a href="?pagina=backup_drive" class="btn btn-primary">Voltar</a>
        </div>
    </div>
</div>
