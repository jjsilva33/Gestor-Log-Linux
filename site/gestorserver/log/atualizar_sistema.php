<?php
if (!isset($_SESSION)) session_start();
$nivel_necessario = 5;
if (!isset($_SESSION['UsuarioID']) || ($_SESSION['UsuarioNivel'] > $nivel_necessario)) {
    session_destroy();
    header("Location: /site/login/index.php");
    exit;
}

$raiz_projeto = dirname(__DIR__, 3);
$saida = '';
$sucesso = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar'])) {
    $comando = 'cd ' . escapeshellarg($raiz_projeto) . ' && git fetch origin 2>&1 && git pull --ff-only origin master 2>&1';
    $saida = shell_exec($comando);
    $sucesso = ($saida !== null);
}

$versao_atual = trim((string) shell_exec('cd ' . escapeshellarg($raiz_projeto) . ' && git log -1 --pretty="%h - %s (%cr)" 2>&1'));
$branch_status = trim((string) shell_exec('cd ' . escapeshellarg($raiz_projeto) . ' && git fetch origin >/dev/null 2>&1 && git rev-list --count HEAD..origin/master 2>&1'));
?>
<div class="container">
    <div class="row clearfix">
        <div class="col-md-12 column">
            <span class="label label-default">Atualizar sistema</span>

            <div class="stat-grid">
                <div class="stat-card accent">
                    <div class="stat-label">Versão instalada</div>
                    <div class="stat-value" style="font-size:15px;"><?php echo htmlspecialchars($versao_atual ?: 'Indisponível'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Commits pendentes</div>
                    <div class="stat-value <?php echo ($branch_status === '0') ? 'ok' : ''; ?>">
                        <?php echo is_numeric($branch_status) ? $branch_status : '?'; ?>
                    </div>
                </div>
            </div>

            <?php if (!is_numeric($branch_status)): ?>
                <div class="notice-box">
                    Não foi possível verificar o repositório git nesta pasta (<?php echo htmlspecialchars($raiz_projeto); ?>).
                    Confirme se é um clone git válido e se o usuário do servidor web (ex: www-data) tem permissão de leitura/escrita e o git instalado.
                </div>
            <?php elseif ($branch_status === '0' && $sucesso === null): ?>
                <p>Já está na versão mais recente do GitHub.</p>
            <?php else: ?>
                <form method="post">
                    <button type="submit" name="atualizar" value="1" class="btn btn-primary">
                        Atualizar agora (<?php echo $branch_status; ?> commit<?php echo ($branch_status == 1 ? '' : 's'); ?> disponíve<?php echo ($branch_status == 1 ? 'l' : 'is'); ?>)
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($sucesso !== null): ?>
                <h4 style="margin-top:22px;">Resultado da atualização</h4>
                <pre style="background:#0e1116; border:1px solid #2c323d; border-radius:8px; padding:14px; color:#c9d1d9; white-space:pre-wrap; max-height:360px; overflow:auto;"><?php echo htmlspecialchars($saida); ?></pre>
                <p style="margin-top:10px;">Pronto. Dê um refresh forçado no navegador (Ctrl+Shift+R) para carregar os arquivos novos sem cache.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<style>
.notice-box {
    background: rgba(245, 166, 35, 0.12);
    border: 1px solid rgba(245, 166, 35, 0.35);
    color: #e4e7ec;
    border-radius: 8px;
    padding: 12px 14px;
    font-size: 13px;
    margin-bottom: 16px;
}
</style>
