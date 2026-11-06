<?php
require_once __DIR__ . '/backuplog/google_drive.php';

$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['salvar_credenciais'])) {
        $client_id = trim($_POST['client_id'] ?? '');
        $client_secret = trim($_POST['client_secret'] ?? '');
        if ($client_id === '' || $client_secret === '') {
            $erro = 'Preencha o Client ID e o Client Secret.';
        } else {
            drive_salvar_credenciais(['client_id' => $client_id, 'client_secret' => $client_secret]);
            $mensagem = 'Credenciais salvas. Agora clique em "Conectar ao Google Drive" abaixo.';
        }
    } elseif (isset($_POST['desconectar'])) {
        drive_desconectar();
        $mensagem = 'Desconectado do Google Drive.';
    }
}

$dados = drive_carregar_credenciais();
$conectado = drive_conectado();
$redirect_uri = drive_redirect_uri();
?>
<div class="container">
    <div class="row clearfix">
        <div class="col-md-12 column">
            <span class="label label-default">Backup na nuvem (Google Drive)</span>

            <div class="stat-grid">
                <div class="stat-card <?php echo $conectado ? 'accent' : ''; ?>">
                    <div class="stat-label">Status</div>
                    <div class="stat-value <?php echo $conectado ? 'ok' : ''; ?>" style="font-size:18px;">
                        <?php echo $conectado ? 'Conectado' : 'Não conectado'; ?>
                    </div>
                </div>
            </div>

            <?php if ($mensagem): ?>
                <div class="notice-box" style="background: rgba(45, 212, 191, 0.12); border-color: rgba(45, 212, 191, 0.35);"><?php echo htmlspecialchars($mensagem); ?></div>
            <?php endif; ?>
            <?php if ($erro): ?>
                <div class="notice-box" style="background: rgba(220, 53, 69, 0.12); border-color: rgba(220, 53, 69, 0.35);"><?php echo htmlspecialchars($erro); ?></div>
            <?php endif; ?>

            <?php if ($conectado): ?>
                <p>Os backups diários e os arquivos de retenção são enviados automaticamente para a pasta <strong>GestorLog-Backups</strong> no Google Drive da conta conectada.</p>
                <form method="post" onsubmit="return confirm('Desconectar? Os próximos backups deixarão de ser enviados para o Drive até reconectar.');">
                    <button type="submit" name="desconectar" value="1" class="btn btn-default">Desconectar</button>
                </form>
            <?php else: ?>

                <h4 style="margin-top:4px;">Passo 1 — Criar as credenciais no Google (feito uma única vez)</h4>
                <ol style="color:#c9d1d9; line-height:1.9; font-size:13px;">
                    <li>Acesse <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener">console.cloud.google.com/apis/credentials</a> e faça login com a conta Gmail onde os backups serão guardados.</li>
                    <li>Se pedir, crie um projeto novo (qualquer nome) e, em "APIs e Serviços &gt; Biblioteca", ative a <strong>Google Drive API</strong>.</li>
                    <li>Em "Tela de permissão OAuth", escolha "Externo", preencha o nome do app e seu e-mail, e adicione esse mesmo e-mail em "Usuários de teste".</li>
                    <li>Em "Credenciais", clique em "Criar credenciais &gt; ID do cliente OAuth", tipo <strong>Aplicativo da Web</strong>.</li>
                    <li>Em "URIs de redirecionamento autorizados", cole exatamente este endereço:
                        <pre style="background:#0e1116; border:1px solid #2c323d; border-radius:8px; padding:10px; color:#2dd4bf; white-space:pre-wrap; margin:6px 0;"><?php echo htmlspecialchars($redirect_uri); ?></pre>
                    </li>
                    <li>Salve e copie o <strong>Client ID</strong> e o <strong>Client Secret</strong> mostrados na tela.</li>
                </ol>

                <h4 style="margin-top:22px;">Passo 2 — Colar as credenciais aqui</h4>
                <form method="post" style="max-width:480px;">
                    <table border="0">
                        <tr>
                            <td>Client ID</td>
                            <td><input type="text" name="client_id" class="form-control" value="<?php echo htmlspecialchars($dados['client_id'] ?? ''); ?>" required></td>
                        </tr>
                        <tr>
                            <td>Client Secret</td>
                            <td><input type="text" name="client_secret" class="form-control" value="<?php echo htmlspecialchars($dados['client_secret'] ?? ''); ?>" required></td>
                        </tr>
                    </table>
                    <button type="submit" name="salvar_credenciais" value="1" class="btn btn-primary" style="margin-top:10px;">Salvar</button>
                </form>

                <?php if (drive_tem_credenciais_app()): ?>
                    <h4 style="margin-top:22px;">Passo 3 — Conectar a conta</h4>
                    <a href="<?php echo htmlspecialchars(drive_url_autorizacao()); ?>" class="btn btn-primary">Conectar ao Google Drive</a>
                    <p style="margin-top:10px; font-size:13px; color:#9aa4b2;">Você será levado ao Google para fazer login e autorizar o acesso, e volta pra essa página em seguida.</p>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
</div>
<style>
.notice-box {
    color: #e4e7ec;
    border: 1px solid;
    border-radius: 8px;
    padding: 12px 14px;
    font-size: 13px;
    margin-bottom: 16px;
}
</style>
