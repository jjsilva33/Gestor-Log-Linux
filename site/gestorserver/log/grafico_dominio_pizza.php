<?php
if (!isset($_SESSION)) {
    session_start();
}
$nivel_necessario = 5;
if (!isset($_SESSION['UsuarioID']) || ($_SESSION['UsuarioNivel'] > $nivel_necessario)) {
    session_destroy();
    header("Location: /site/login/index.php");
    exit;
}
?>
<?php set_time_limit(0); ?>
<?php
require_once('../../Connections/site.php');
date_default_timezone_set('America/Campo_Grande');
$iduser = $_SESSION['iduser'];
$data1 = $_REQUEST['data1'];
$data2 = $_REQUEST['data2'];
$tipo = $_REQUEST['tipo'];
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

    $totalfiltro = "SELECT Message, Count(Message) AS ContMessage FROM SystemEvents WHERE `Message` LIKE :ip AND `SysLogTag`='web-proxy,account' and `ReceivedAt`>= :data1 AND `ReceivedAt`<= :data2 GROUP BY Message ORDER BY `ContMessage` DESC LIMIT 0,30;";
    $stmt_totalfiltro = $pdo->prepare($totalfiltro);
    $stmt_totalfiltro->bindValue(':ip', "%$ip%");
    $stmt_totalfiltro->bindValue(':data1', "$data1 00:00:00");
    $stmt_totalfiltro->bindValue(':data2', "$data2 23:59:59");
    $stmt_totalfiltro->execute();
    $resultotalfiltro = $stmt_totalfiltro->fetchAll(PDO::FETCH_ASSOC);

    $array = array();

    foreach ($resultotalfiltro as $row_rslistafiltrografico2) {
        $nomefiltrocategoria = $row_rslistafiltrografico2['Message'];
        $posicao = strpos($nomefiltrocategoria, 'http://');
        if ($posicao !== false && $posicao > 0) {
            $texto = substr($nomefiltrocategoria, $posicao + 7);
            $string = explode("/", $texto);
            $nomefiltrocategoria = $string[0];
            $array[] = $nomefiltrocategoria;
        }
        $posicao = strpos($nomefiltrocategoria, 'https://');
        if ($posicao !== false && $posicao > 0) {
            $texto = substr($nomefiltrocategoria, $posicao + 8);
            $string = explode("/", $texto);
            $nomefiltrocategoria = $string[0];
            $array[] = $nomefiltrocategoria;
        }
        $posicao = strpos($nomefiltrocategoria, 'CONNECT');
        if ($posicao !== false && $posicao > 0) {
            $texto = substr($nomefiltrocategoria, $posicao + 8);
            $string = explode(" ", $texto);
            $nomefiltrocategoria = $string[0];
            $array[] = $nomefiltrocategoria;
        }
    }

    $array = array_unique($array);
    $dadospizza = array();

    foreach ($array as $dominiobusca) {
        $queryfiltro = "SELECT COUNT(*) AS Total FROM SystemEvents WHERE `Message` LIKE :dominiobusca AND `Message` LIKE :ip AND `ReceivedAt`>= :data1 AND `ReceivedAt`<= :data2";
        $stmt_filtro = $pdo->prepare($queryfiltro);
        $stmt_filtro->bindValue(':dominiobusca', "%$dominiobusca%");
        $stmt_filtro->bindValue(':ip', "%$ip%");
        $stmt_filtro->bindValue(':data1', "$data1 00:00:00");
        $stmt_filtro->bindValue(':data2', "$data2 23:59:59");
        $stmt_filtro->execute();
        $row = $stmt_filtro->fetch(PDO::FETCH_ASSOC);

        $resultadototalfiltro = (int) $row['Total'];

        if ($resultadototalfiltro > 0) {
            $dadospizza[] = array($dominiobusca, $resultadototalfiltro);
        }
    }
} catch (PDOException $e) {
    echo "Failed to connect to MySQL: " . $e->getMessage();
    exit();
}

$titulo_grafico = 'Utilização por domínio';
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
                                    <?php
                                    echo '<option value="">Todos</option>';
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
                                    <option value="pizza" selected>Pizza</option>
                                    <option value="funil">Funil</option>
                                </select>
                            </td>
                            <td>De:</td>
                            <td><input type="date" name="data1" value="<?php echo htmlspecialchars($data1); ?>"></td>
                            <td>Até:</td>
                            <td><input type="date" name="data2" value="<?php echo htmlspecialchars($data2); ?>"></td>
                            <td><input type="submit" value="ok" /></td>
                        </tr>
                    </tbody>
                </table>
            </form>

            <div class="chart-card" style="margin-top:18px;">
                <div class="chart-card-title"><?php echo htmlspecialchars($titulo_grafico); ?></div>
                <?php if (empty($dadospizza)): ?>
                    <p style="text-align:center; padding:60px 0;">Nenhum acesso registrado no período selecionado.</p>
                <?php else: ?>
                    <div id="graficoPizzaDominio" class="chart-box" style="height:460px;"></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($dadospizza)): ?>
<script src="https://code.highcharts.com/highcharts.js"></script>
<script>
Highcharts.setOptions({
    chart: { backgroundColor: 'transparent', style: { fontFamily: 'Inter, sans-serif' } },
    title: { style: { color: '#e4e7ec' } },
    credits: { enabled: false },
    legend: { itemStyle: { color: '#8b93a1' }, itemHoverStyle: { color: '#e4e7ec' } }
});

Highcharts.chart('graficoPizzaDominio', {
    chart: { type: 'pie' },
    title: { text: '' },
    tooltip: { pointFormat: '{series.name}: <b>{point.percentage:.1f}%</b> ({point.y})' },
    plotOptions: {
        pie: {
            allowPointSelect: true,
            cursor: 'pointer',
            borderWidth: 1,
            borderColor: '#15181e',
            dataLabels: {
                enabled: true,
                style: { color: '#e4e7ec', textOutline: 'none', fontSize: '11px' },
                format: '{point.name}: {point.percentage:.1f}%'
            }
        }
    },
    series: [{
        name: 'Acessos',
        colorByPoint: true,
        data: <?php echo json_encode($dadospizza); ?>
    }]
});
</script>
<?php endif; ?>
