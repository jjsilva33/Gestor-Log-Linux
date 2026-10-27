<?php
// A sessão precisa ser iniciada em cada página diferente
if (!isset($_SESSION))
    session_start();
$nivel_necessario = 5;
// Verifica se não há a variável da sessão que identifica o usuário
if (!isset($_SESSION['UsuarioID']) || ($_SESSION['UsuarioNivel'] > $nivel_necessario)) {
    // Destrói a sessão por segurança
    session_destroy();
    // Redireciona o visitante de volta para o login
    header("Location: /site/login/index.php");
    exit;
}
?>
<?php set_time_limit(0); ?>
<?php
require_once('../../Connections/site.php');
$iduser = $_SESSION['iduser'];
$data1 = $_REQUEST['data1'];
$data2 = $_REQUEST['data2'];
$tipo = $_REQUEST['tipo'];
$conta = $_REQUEST['conta'];
$ip = $_REQUEST['ipusuariolog'];
$usuariolog = '';

if ($data1 == '') {
    $data1 = date('Y-m-d', strtotime('-30 days'));
}
if ($data2 == '') {
    $data2 = date('Y-m-d');
}

try {

    $sqlselecionausuariolog = "SELECT * FROM `usuario_log` ";
    $stmt_selecionausuariolog = $pdo->query($sqlselecionausuariolog);
    $row_rsselecionausuariolog = $stmt_selecionausuariolog->fetchAll(PDO::FETCH_ASSOC);

    if ($ip != "") {
        $sqlselecionausuariolog2 = "SELECT * FROM `usuario_log` where `ipusuariolog`= :ip";
        $stmt_selecionausuariolog2 = $pdo->prepare($sqlselecionausuariolog2);
        $stmt_selecionausuariolog2->bindValue(':ip', $ip);
        $stmt_selecionausuariolog2->execute();
        $row_rsselecionausuariolog2 = $stmt_selecionausuariolog2->fetch(PDO::FETCH_ASSOC);
        $usuariolog = $row_rsselecionausuariolog2['usuariolog'];
    }

    $queryfiltro = "SELECT Message FROM SystemEvents WHERE `Message` LIKE :ip AND `SysLogTag` LIKE 'web-proxy,account' AND `ReceivedAt` >= :data1 AND `ReceivedAt` <= :data2 GROUP BY Message ORDER BY `Message`";
    $stmt_filtro = $pdo->prepare($queryfiltro);
    $stmt_filtro->bindValue(':ip', "%$ip%");
    $stmt_filtro->bindValue(':data1', "$data1 00:00:00");
    $stmt_filtro->bindValue(':data2', "$data2 23:59:59");
    $stmt_filtro->execute();
    $resultfiltro = $stmt_filtro->fetchAll(PDO::FETCH_ASSOC);

    $acessos = array();

    foreach ($resultfiltro as $row_rslistafiltrografico2) {
        $dominio = $row_rslistafiltrografico2['Message'];
        $nomefiltrocategoria = null;

        $posicao = strpos($dominio, 'http://');
        if ($posicao !== false && $posicao > 0) {
            $texto = substr($dominio, $posicao + 7);
            $string = explode("/", $texto);
            $nomefiltrocategoria = $string[0];
        } else {
            $posicao = strpos($dominio, 'https://');
            if ($posicao !== false && $posicao > 0) {
                $texto = substr($dominio, $posicao + 8);
                $string = explode("/", $texto);
                $nomefiltrocategoria = $string[0];
            } else {
                $posicao = strpos($dominio, 'CONNECT');
                if ($posicao !== false && $posicao > 0) {
                    $texto = substr($dominio, $posicao + 8);
                    $string = explode(" ", $texto);
                    $nomefiltrocategoria = $string[0];
                }
            }
        }

        if ($nomefiltrocategoria !== null && $nomefiltrocategoria !== '') {
            $acessos[] = $nomefiltrocategoria;
        }
    }

    $acessos_cont = array_count_values($acessos);
    arsort($acessos_cont);
    $dadosfunil = array();
    foreach (array_slice($acessos_cont, 0, 30, true) as $chave => $conteudo) {
        $dadosfunil[] = array($chave, $conteudo);
    }
} catch (PDOException $e) {
    echo "Failed to connect to MySQL: " . $e->getMessage();
    exit();
}

$titulo_grafico = 'Utilização por domínio (funil)';
if ($usuariolog !== '') {
    $titulo_grafico .= " — $usuariolog";
}
if ($ip !== '') {
    $titulo_grafico .= " (IP: $ip)";
}
?>

<div class="container">
    <div class="row clearfix">
        <div class="col-md-12 column">
            <span class="label label-default">Estatística de acesso</span>

            <form action="?pagina=estatistica" method="POST">
                <table border="0" align="left">
                    <tbody>
                        <tr>
                            <td>Usuario:</td>
                            <td>
                                <select name="ipusuariolog" id="usuariolog">
                                    <option value="">Todos</option>
                                    <?php
                                    foreach ($row_rsselecionausuariolog as $row) {
                                        $selecionado = ($row['ipusuariolog'] == $ip) ? ' selected' : '';
                                        echo '<option value="' . $row['ipusuariolog'] . '"' . $selecionado . '>' . $row['usuariolog'] . '</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                            <td>Tipo:</td>
                            <td>
                                <select name="tipografico" id="tipografico">
                                    <option value="pizza">Pizza</option>
                                    <option value="funil" selected>Funil</option>
                                </select>
                            </td>
                            <td>De:</td>
                            <td><input type="date" name="data1" value="<?php echo htmlspecialchars($data1); ?>"></td>
                            <td>Até:</td>
                            <td><input type="date" name="data2" value="<?php echo htmlspecialchars($data2); ?>"></td>
                            <td><input type="submit" value="ok"></td>
                        </tr>
                    </tbody>
                </table>
            </form>

            <div class="chart-card" style="margin-top:18px;">
                <div class="chart-card-title"><?php echo htmlspecialchars($titulo_grafico); ?></div>
                <?php if (empty($dadosfunil)): ?>
                    <p style="text-align:center; padding:60px 0;">Nenhum acesso registrado no período selecionado.</p>
                <?php else: ?>
                    <div id="graficoFunilDominio" class="chart-box" style="height:460px; max-width:640px; margin:0 auto;"></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($dadosfunil)): ?>
<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/modules/funnel.js"></script>
<script>
Highcharts.setOptions({
    chart: { backgroundColor: 'transparent', style: { fontFamily: 'Inter, sans-serif' } },
    title: { style: { color: '#e4e7ec' } },
    credits: { enabled: false }
});

Highcharts.chart('graficoFunilDominio', {
    chart: { type: 'funnel' },
    title: { text: '' },
    plotOptions: {
        series: {
            dataLabels: {
                enabled: true,
                format: '<b>{point.name}</b> ({point.y:,.0f})',
                color: '#e4e7ec',
                softConnector: true
            },
            center: ['40%', '50%'],
            neckWidth: '20%',
            neckHeight: '0%',
            width: '70%'
        }
    },
    legend: { enabled: false },
    series: [{
        name: 'Visitas',
        data: <?php echo json_encode($dadosfunil); ?>
    }]
});
</script>
<?php endif; ?>
