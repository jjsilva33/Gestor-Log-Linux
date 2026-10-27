<?php

if (!isset($_SESSION)) session_start();
$nivel_necessario = 5;
// Verifica se n?o h? a vari?vel da sess?o que identifica o usu?rio
if (!isset($_SESSION['UsuarioID']) OR ($_SESSION['UsuarioNivel'] > $nivel_necessario)) {
	// Destr?i a sess?o por seguran?a
	session_destroy();
	// Redireciona o visitante de volta pro login
	header("Location: /site/login/"); exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Gestor Log</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="">
  <meta name="author" content="">

	<!--link rel="stylesheet/less" href="less/bootstrap.less" type="text/css" /-->
	<!--link rel="stylesheet/less" href="less/responsive.less" type="text/css" /-->
	<!--script src="js/less-1.3.3.min.js"></script-->
	<!--append �#!watch� to the browser URL, then refresh the page. -->

	<link href="css/bootstrap.min.css" rel="stylesheet">
	<link href="css/style.css" rel="stylesheet">

  <!-- HTML5 shim, for IE6-8 support of HTML5 elements -->
  <!--[if lt IE 9]>
    <script src="js/html5shiv.js"></script>
  <![endif]-->

  <!-- Fav and touch icons -->
  <link rel="apple-touch-icon-precomposed" sizes="144x144" href="img/apple-touch-icon-144-precomposed.png">
  <link rel="apple-touch-icon-precomposed" sizes="114x114" href="img/apple-touch-icon-114-precomposed.png">
  <link rel="apple-touch-icon-precomposed" sizes="72x72" href="img/apple-touch-icon-72-precomposed.png">
  <link rel="apple-touch-icon-precomposed" href="img/apple-touch-icon-57-precomposed.png">
  <link rel="shortcut icon" href="img/favicon.png">

	<script type="text/javascript" src="js/jquery.min.js"></script>
	<script type="text/javascript" src="js/bootstrap.min.js"></script>
	<script type="text/javascript" src="js/scripts.js"></script>
</head>

<body>
<?php
$pagina_atual = $_REQUEST['pagina'];
function nav_active($alvo, $atual) {
	return ($atual === $alvo) ? ' active' : '';
}
?>
<button type="button" class="app-sidebar-toggle" id="appSidebarToggle" aria-label="Abrir menu">
	<span></span><span></span><span></span>
</button>
<div class="app-shell">
	<aside class="app-sidebar" id="appSidebar">
		<a class="app-sidebar-brand" href="?pagina=status">
			<span class="dot"></span> Status do Log
		</a>

		<nav class="app-sidebar-nav">
			<a href="/site/gestorserver/log/?pagina=relatorio" class="<?php echo trim('nav-link' . nav_active('relatorio', $pagina_atual)); ?>">Relatorio</a>
			<a href="/site/gestorserver/log/?pagina=estatistica" class="<?php echo trim('nav-link' . nav_active('estatistica', $pagina_atual)); ?>">Estatistica</a>
			<a href="/site/gestorserver/log/?pagina=rank" class="<?php echo trim('nav-link' . nav_active('rank', $pagina_atual)); ?>">Top Rank</a>

			<div class="app-sidebar-section">Configurar</div>
			<a href="?pagina=adicionar_usuario" class="<?php echo trim('nav-link' . nav_active('adicionar_usuario', $pagina_atual)); ?>">Vincular usuario a IP</a>
			<a href="?pagina=manutencao" class="<?php echo trim('nav-link' . nav_active('manutencao', $pagina_atual)); ?>">Manutencao do log</a>
			<a href="?pagina=atualizar" class="<?php echo trim('nav-link' . nav_active('atualizar', $pagina_atual)); ?>">Atualizar sistema</a>
			<a href="https://www.bylltec.com.br/site/home.php?pagina=manual" target="_blank" rel="noopener">Manual</a>
		</nav>

		<div class="app-sidebar-footer">
			<a href="https://www.bylltec.com.br/site/home.php?pagina=suporte&sistema=gestorlog" target="_blank" rel="noopener">Suporte &middot; v2.1.1</a>
			<a href="/site/login/logout.php">Sair</a>
		</div>
	</aside>

	<div class="app-sidebar-overlay" id="appSidebarOverlay"></div>

	<main class="app-main">
	<?php
$pagina = $_REQUEST['pagina'];
$tipografico = $_REQUEST['tipografico'];

if ($pagina ==''){

  require('./home.php');
 }
 if ($pagina =='manutencao'){

  require('../../admin/redefinir_senha.php');
 }
 if ($pagina =='status'){

  require('./status.php');
 }
 if ($pagina =='relatorio'){

  require('./relatorio_log.php');
 }
 if ($pagina =='rank'){

  require('./top_rank.php');
 }

 if ($pagina =='estatistica'){
     if($tipografico==''){
  require('./grafico_dominio_pizza.php');
 }
 if($tipografico=='pizza'){
  require('./grafico_dominio_pizza.php');
 }
 if($tipografico=='funil'){
     require './grafico_funil_usuario.php';
 }
 }

 if ($pagina =='adicionar_usuario'){

  require('./add_novo_usuario.php');
    require('./usuarios_cadastrados.php');
 }

 if ($pagina =='atualizar'){

  require('./atualizar_sistema.php');
 }

 ?>
	</main>
</div>
<script>
(function () {
	var sidebar = document.getElementById('appSidebar');
	var toggle = document.getElementById('appSidebarToggle');
	var overlay = document.getElementById('appSidebarOverlay');
	function closeSidebar() {
		sidebar.classList.remove('open');
		overlay.classList.remove('visible');
	}
	toggle.addEventListener('click', function () {
		sidebar.classList.toggle('open');
		overlay.classList.toggle('visible');
	});
	overlay.addEventListener('click', closeSidebar);
})();
</script>
</body>
</html>
