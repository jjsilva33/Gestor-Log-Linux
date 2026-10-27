<?php
// A sessão precisa ser iniciada em cada página diferente
if (!isset($_SESSION)) session_start();
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

<?php
require '../../Connections/site.php';

try {
  
    $sqlbuscausuario = "SELECT * FROM `usuario_log`";
    $querybuscausuario = $pdo->query($sqlbuscausuario);
    $totalusuariolog = $querybuscausuario->rowCount();

    $espaco_disco = disk_free_space(".");
    $si_prefix = array('B', 'KB', 'MB', 'GB', 'TB', 'EB', 'ZB', 'YB');
    $base = 1024;
    $class = min((int)log($espaco_disco, $base), count($si_prefix) - 1);
    $espaco_disco_formatado = sprintf('%1.2f', $espaco_disco / pow($base, $class)) . ' ' . $si_prefix[$class];

    $disco_total = disk_total_space("/");
    $class = min((int)log($disco_total, $base), count($si_prefix) - 1);
    $disco_total_formatado = sprintf('%1.2f', $disco_total / pow($base, $class)) . ' ' . $si_prefix[$class];

    // Acessos por dia (últimos 30 dias)
    $sqlacessospordia = "SELECT DATE(ReceivedAt) AS dia, COUNT(*) AS total FROM SystemEvents WHERE ReceivedAt >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(ReceivedAt) ORDER BY dia ASC";
    $queryacessospordia = $pdo->query($sqlacessospordia);
    $categoriasdias = array();
    $totaispordia = array();
    while ($row = $queryacessospordia->fetch(PDO::FETCH_ASSOC)) {
        $categoriasdias[] = date('d/m', strtotime($row['dia']));
        $totaispordia[] = (int) $row['total'];
    }

    // Top domínios acessados (últimos 30 dias)
    $sqltopdominios = "SELECT Message, COUNT(Message) AS ContMessage FROM SystemEvents WHERE `SysLogTag`='web-proxy,account' AND ReceivedAt >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY Message ORDER BY ContMessage DESC LIMIT 0,200";
    $querytopdominios = $pdo->query($sqltopdominios);
    $dominiosbrutos = array();
    while ($row = $querytopdominios->fetch(PDO::FETCH_ASSOC)) {
        $texto = $row['Message'];
        $posicao = strpos($texto, 'http://');
        if ($posicao !== false) {
            $resto = explode('/', substr($texto, $posicao + 7));
            $dominio = $resto[0];
        } else {
            $posicao = strpos($texto, 'https://');
            if ($posicao !== false) {
                $resto = explode('/', substr($texto, $posicao + 8));
                $dominio = $resto[0];
            } else {
                continue;
            }
        }
        if ($dominio === '') {
            continue;
        }
        if (!isset($dominiosbrutos[$dominio])) {
            $dominiosbrutos[$dominio] = 0;
        }
        $dominiosbrutos[$dominio] += (int) $row['ContMessage'];
    }
    arsort($dominiosbrutos);
    $dominiosbrutos = array_slice($dominiosbrutos, 0, 12, true);
    $dadospizzadominios = array();
    foreach ($dominiosbrutos as $dominio => $total) {
        $dadospizzadominios[] = array($dominio, $total);
    }
} catch (PDOException $e) {
    echo "Failed to connect to MySQL: " . $e->getMessage();
    exit();
}
?>

<div class="container">
    <div class="row clearfix">
        <div class="col-md-12 column">
            <span class="label label-default">Status do log</span>

            <div class="stat-grid">
                <div class="stat-card accent">
                    <div class="stat-label">Total de usuários</div>
                    <div class="stat-value"><?php echo $totalusuariolog; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Status</div>
                    <div class="stat-value ok">Ativo</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Espaço livre</div>
                    <div class="stat-value"><?php echo $espaco_disco_formatado; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Espaço total</div>
                    <div class="stat-value"><?php echo $disco_total_formatado; ?></div>
                </div>
            </div>

            <div class="chart-grid">
                <div class="chart-card">
                    <div class="chart-card-title">Acessos por dia (últimos 30 dias)</div>
                    <?php if (empty($totaispordia)): ?>
                        <p style="text-align:center; padding:60px 0;">Nenhum registro nos últimos 30 dias.</p>
                    <?php else: ?>
                        <div id="graficoAcessosDia" class="chart-box"></div>
                    <?php endif; ?>
                </div>
                <div class="chart-card">
                    <div class="chart-card-title">Domínios mais acessados</div>
                    <?php if (empty($dadospizzadominios)): ?>
                        <p style="text-align:center; padding:60px 0;">Nenhum acesso de domínio (web-proxy) registrado nos últimos 30 dias.</p>
                    <?php else: ?>
                        <div id="graficoDominios" class="chart-box"></div>
                    <?php endif; ?>
                </div>
            </div>

            <?php
            require("backuplog/listar_arquivos.php");
            ?>
        </div>
    </div>
</div>

<?php if (!empty($totaispordia) || !empty($dadospizzadominios)): ?>
<script src="https://code.highcharts.com/highcharts.js"></script>
<script>
Highcharts.setOptions({
    chart: { backgroundColor: 'transparent', style: { fontFamily: 'Inter, sans-serif' } },
    title: { style: { color: '#e4e7ec' } },
    credits: { enabled: false },
    legend: { itemStyle: { color: '#8b93a1' }, itemHoverStyle: { color: '#e4e7ec' } }
});

<?php if (!empty($totaispordia)): ?>
Highcharts.chart('graficoAcessosDia', {
    chart: { type: 'column' },
    title: { text: '' },
    xAxis: {
        categories: <?php echo json_encode($categoriasdias); ?>,
        labels: { style: { color: '#8b93a1' } },
        lineColor: '#2c323d',
        tickColor: '#2c323d'
    },
    yAxis: {
        title: { text: '' },
        gridLineColor: '#242931',
        labels: { style: { color: '#8b93a1' } }
    },
    plotOptions: {
        column: { maxPointWidth: 56 }
    },
    series: [{
        name: 'Registros',
        data: <?php echo json_encode($totaispordia); ?>,
        color: '#2dd4bf',
        borderRadius: 3,
        borderWidth: 0
    }]
});
<?php endif; ?>

<?php if (!empty($dadospizzadominios)): ?>
Highcharts.chart('graficoDominios', {
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
        data: <?php echo json_encode($dadospizzadominios); ?>
    }]
});
<?php endif; ?>
</script>
<?php endif; ?>





</body>
</html>


<html>
<head>

<style>
#pop{background-color:#b0c4de;display:none;position:absolute;top:50%;left:50%;margin-left:-150px;margin-top:-100px;padding:10px;width:auto;height:auto;border:1px solid #d0d0d0}

</style>
</head>
<body>
<div id="pop">
<a href="#" onClick="document.getElementById('pop').style.display='none';"></a>
<br />


		<p class="align-center"><img src="/site/images/progresso.gif" width="20" height="20" alt="progress"/>Aguarde... </p>
	<p>Processando Backup</p>

</div>


<html>
<meta http-equiv="refresh" content="120;URL=/site/gestorserver/log/?pagina=status">
</html>
