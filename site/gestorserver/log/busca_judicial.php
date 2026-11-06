<?php
require_once '../../Connections/site.php';

$ip_publico = trim($_GET['ip_publico'] ?? '');
$porta_publica_raw = trim($_GET['porta_publica'] ?? '');
$data_hora = trim($_GET['data_hora'] ?? ''); // formato datetime-local: AAAA-MM-DDTHH:MM
$data = trim($_GET['data'] ?? '');           // formato date: AAAA-MM-DD
$solicitante = trim($_GET['solicitante'] ?? '');

$pesquisou = ($ip_publico !== '' && $porta_publica_raw !== '');
$erro = '';
$resultados = [];
$usado_fallback = false;
$modo = 'recentes';

if ($pesquisou) {
    $porta_publica = (int) $porta_publica_raw;

    if ($data_hora !== '') {
        $modo = 'exato';
        $ts = str_replace('T', ' ', $data_hora);
        if (strlen($ts) === 16) { // "AAAA-MM-DD HH:MM" sem segundos
            $ts .= ':00';
        }
        if (DateTime::createFromFormat('Y-m-d H:i:s', $ts) === false) {
            $erro = 'Data/hora inválida.';
        } else {
            $stmt = $pdo->prepare(
                "SELECT * FROM nat_logs
                 WHERE ip_publico = :ip AND porta_publica = :porta
                   AND ts_amostra <= :ts AND COALESCE(ts_fim, ts_amostra) >= :ts
                 ORDER BY ts_amostra DESC"
            );
            $stmt->execute([':ip' => $ip_publico, ':porta' => $porta_publica, ':ts' => $ts]);
            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$resultados) {
                $usado_fallback = true;
                $stmt = $pdo->prepare(
                    "SELECT * FROM nat_logs
                     WHERE ip_publico = :ip AND porta_publica = :porta AND ts_amostra <= :ts
                     ORDER BY ts_amostra DESC LIMIT 5"
                );
                $stmt->execute([':ip' => $ip_publico, ':porta' => $porta_publica, ':ts' => $ts]);
                $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } elseif ($data !== '') {
        $modo = 'dia';
        if (DateTime::createFromFormat('Y-m-d', $data) === false) {
            $erro = 'Data inválida.';
        } else {
            $inicio_dia = $data . ' 00:00:00';
            $fim_dia = date('Y-m-d', strtotime($data . ' +1 day')) . ' 00:00:00';
            $stmt = $pdo->prepare(
                "SELECT * FROM nat_logs
                 WHERE ip_publico = :ip AND porta_publica = :porta
                   AND ts_amostra < :fim AND COALESCE(ts_fim, ts_amostra) >= :inicio
                 ORDER BY ts_amostra DESC LIMIT 200"
            );
            $stmt->execute([':ip' => $ip_publico, ':porta' => $porta_publica, ':inicio' => $inicio_dia, ':fim' => $fim_dia]);
            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        $stmt = $pdo->prepare(
            "SELECT * FROM nat_logs WHERE ip_publico = :ip AND porta_publica = :porta ORDER BY ts_amostra DESC LIMIT 50"
        );
        $stmt->execute([':ip' => $ip_publico, ':porta' => $porta_publica]);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($erro === '') {
        $momento_pesquisado = ($modo === 'exato') ? $ts : (($modo === 'dia') ? $data . ' 00:00:00' : null);
        try {
            $stmt_auditoria = $pdo->prepare(
                "INSERT INTO nat_logs_auditoria (usuario, solicitante, ip_pesquisado, porta_pesquisada, momento_pesquisado, encontrado)
                 VALUES (:usuario, :solicitante, :ip, :porta, :momento, :encontrado)"
            );
            $stmt_auditoria->execute([
                ':usuario' => $_SESSION['UsuarioNome'] ?? 'desconhecido',
                ':solicitante' => $solicitante !== '' ? $solicitante : null,
                ':ip' => $ip_publico,
                ':porta' => $porta_publica,
                ':momento' => $momento_pesquisado,
                ':encontrado' => $resultados ? 1 : 0,
            ]);
        } catch (PDOException $e) {
            // Tabela de auditoria pode ainda não existir num servidor que não rodou a
            // migração mais recente de nat_logs.sql -- não deve impedir a busca em si.
        }
    }
}

// Cruza o IP privado encontrado com a tabela de usuários cadastrados por IP (melhor
// esforço: essa tabela normalmente serve para IPs fixos internos, não para o pool
// dinâmico do CGNAT, então o nome só aparece quando bate por coincidência).
$nomes_cadastrados = [];
if ($resultados) {
    $stmt_nomes = $pdo->query('SELECT ipusuariolog, usuariolog FROM usuario_log');
    foreach ($stmt_nomes->fetchAll(PDO::FETCH_ASSOC) as $linha) {
        $nomes_cadastrados[$linha['ipusuariolog']] = $linha['usuariolog'];
    }
}
?>
<div class="container">
    <div class="row clearfix">
        <div class="col-md-12 column">
            <span class="label label-default">Busca judicial (NAT)</span>
            <p>Informe o IP público e a porta citados no ofício, e a data/hora exata (ou só a data, se não souber o horário) — tudo em UTC.</p>

            <form action="" method="GET">
                <input type="hidden" name="pagina" value="busca_judicial">
                <table border="0" align="center">
                    <tbody>
                        <tr>
                            <td>IP público:</td>
                            <td><input type="text" name="ip_publico" value="<?php echo htmlspecialchars($ip_publico); ?>" placeholder="200.200.200.222" required></td>
                            <td>Porta pública:</td>
                            <td><input type="number" name="porta_publica" value="<?php echo htmlspecialchars($porta_publica_raw); ?>" placeholder="43211" required></td>
                        </tr>
                        <tr>
                            <td>Data/hora exata (UTC):</td>
                            <td><input type="datetime-local" name="data_hora" value="<?php echo htmlspecialchars($data_hora); ?>"></td>
                            <td>Ou só a data (UTC):</td>
                            <td><input type="date" name="data" value="<?php echo htmlspecialchars($data); ?>"></td>
                        </tr>
                        <tr>
                            <td>Solicitante/ofício:</td>
                            <td colspan="3"><input type="text" name="solicitante" value="<?php echo htmlspecialchars($solicitante); ?>" placeholder="Ex: Ofício nº 123/2026 - Delegacia X" style="width:100%;"></td>
                        </tr>
                        <tr>
                            <td><input type="submit" value="Buscar"></td>
                        </tr>
                    </tbody>
                </table>
            </form>

            <?php if ($erro): ?>
                <div class="notice-box" style="background: rgba(220, 53, 69, 0.12); border-color: rgba(220, 53, 69, 0.35); color:#e4e7ec; border:1px solid; border-radius:8px; padding:12px 14px; font-size:13px; margin:16px 0;"><?php echo htmlspecialchars($erro); ?></div>
            <?php elseif ($pesquisou): ?>

                <?php if ($usado_fallback): ?>
                    <div class="notice-box" style="background: rgba(245, 166, 35, 0.12); border-color: rgba(245, 166, 35, 0.35); color:#e4e7ec; border:1px solid; border-radius:8px; padding:12px 14px; font-size:13px; margin:16px 0;">
                        Nenhuma sessão cobre exatamente esse horário. Mostrando as sessões mais próximas encontradas para esse IP:porta.
                    </div>
                <?php elseif (!$resultados): ?>
                    <p style="margin-top:16px;">Nenhum registro encontrado para esse IP público/porta<?php echo ($modo === 'dia') ? ' nessa data' : (($modo === 'exato') ? ' nesse horário' : ''); ?>.</p>
                <?php endif; ?>

                <?php if ($resultados): ?>
                    <table class="table" style="margin-top:16px;">
                        <thead>
                            <tr>
                                <th>IP privado</th>
                                <th>Porta privada</th>
                                <th>Protocolo</th>
                                <th>Início (UTC)</th>
                                <th>Fim (UTC)</th>
                                <th>Usuário cadastrado nesse IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resultados as $linha): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($linha['ip_privado']); ?></td>
                                    <td><?php echo htmlspecialchars($linha['porta_privada']); ?></td>
                                    <td><?php echo htmlspecialchars($linha['protocolo']); ?></td>
                                    <td><?php echo htmlspecialchars($linha['ts_amostra']); ?></td>
                                    <td><?php echo htmlspecialchars($linha['ts_fim'] ?? $linha['ts_amostra']); ?></td>
                                    <td><?php echo htmlspecialchars($nomes_cadastrados[$linha['ip_privado']] ?? '—'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p style="font-size:12px; color:#9aa4b2; margin-top:8px;">
                        "Usuário cadastrado" é só um cruzamento de melhor esforço com IPs fixos cadastrados em Configurações — o pool do CGNAT costuma ser dinâmico, então a identificação do cliente final normalmente depende do sistema de gerência de assinantes (RADIUS/PPPoE) na mesma janela de tempo.
                    </p>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
</div>
