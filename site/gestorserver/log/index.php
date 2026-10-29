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
$nome_usuario = isset($_SESSION['UsuarioNome']) && $_SESSION['UsuarioNome'] !== '' ? $_SESSION['UsuarioNome'] : 'Usuário';
$iniciais_usuario = '';
foreach (array_slice(explode(' ', trim($nome_usuario)), 0, 2) as $parte) {
	if ($parte !== '') { $iniciais_usuario .= mb_strtoupper(mb_substr($parte, 0, 1)); }
}
if ($iniciais_usuario === '') { $iniciais_usuario = 'U'; }

$paginas_config = array('adicionar_usuario', 'manutencao', 'atualizar');
$config_aberta = in_array($pagina_atual, $paginas_config, true);
?>
<button type="button" class="app-sidebar-toggle" id="appSidebarToggle" aria-label="Abrir menu">
	<span></span><span></span><span></span>
</button>
<div class="app-shell">
	<aside class="app-sidebar" id="appSidebar">
		<div class="app-sidebar-user">
			<div class="app-sidebar-avatar"><?php echo htmlspecialchars($iniciais_usuario); ?></div>
			<div class="app-sidebar-user-info">
				<div class="app-sidebar-user-name"><?php echo htmlspecialchars($nome_usuario); ?></div>
				<div class="app-sidebar-user-role">Administrador</div>
			</div>
			<button type="button" class="app-sidebar-collapse" id="appSidebarCollapse" aria-label="Recolher menu">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
			</button>
		</div>

		<nav class="app-sidebar-nav">
			<a href="?pagina=status" class="<?php echo trim('nav-link' . nav_active('status', $pagina_atual) . nav_active('', $pagina_atual)); ?>">
				<span class="nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1.5"></rect><rect x="14" y="3" width="7" height="5" rx="1.5"></rect><rect x="14" y="12" width="7" height="9" rx="1.5"></rect><rect x="3" y="16" width="7" height="5" rx="1.5"></rect></svg></span>
				<span class="nav-label">Dashboard</span>
			</a>
			<a href="/site/gestorserver/log/?pagina=relatorio" class="<?php echo trim('nav-link' . nav_active('relatorio', $pagina_atual)); ?>">
				<span class="nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="8" y1="13" x2="16" y2="13"></line><line x1="8" y1="17" x2="16" y2="17"></line></svg></span>
				<span class="nav-label">Relatorio</span>
			</a>
			<a href="/site/gestorserver/log/?pagina=estatistica" class="<?php echo trim('nav-link' . nav_active('estatistica', $pagina_atual)); ?>">
				<span class="nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg></span>
				<span class="nav-label">Estatistica</span>
			</a>
			<a href="/site/gestorserver/log/?pagina=rank" class="<?php echo trim('nav-link' . nav_active('rank', $pagina_atual)); ?>">
				<span class="nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"></circle><path d="M8.5 13.5 7 22l5-3 5 3-1.5-8.5"></path></svg></span>
				<span class="nav-label">Top Rank</span>
			</a>

			<button type="button" class="app-sidebar-section<?php echo $config_aberta ? ' open' : ''; ?>" id="appSidebarConfigToggle">
				<span class="nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg></span>
				<span class="nav-label">Configurações</span>
				<svg class="nav-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
			</button>
			<div class="app-sidebar-submenu<?php echo $config_aberta ? ' open' : ''; ?>" id="appSidebarConfigMenu">
				<a href="?pagina=adicionar_usuario" class="<?php echo trim('nav-sublink' . nav_active('adicionar_usuario', $pagina_atual)); ?>">Vincular usuario a IP</a>
				<a href="?pagina=manutencao" class="<?php echo trim('nav-sublink' . nav_active('manutencao', $pagina_atual)); ?>">Manutencao do log</a>
				<a href="?pagina=atualizar" class="<?php echo trim('nav-sublink' . nav_active('atualizar', $pagina_atual)); ?>">Atualizar sistema</a>
			</div>
		</nav>

		<div class="app-sidebar-footer">
			<a href="#">
				<span class="nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18v-6a9 9 0 0 1 18 0v6"></path><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path></svg></span>
				<span class="nav-label">Suporte &middot; v2.1.1</span>
			</a>
			<a href="/site/login/logout.php">
				<span class="nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg></span>
				<span class="nav-label">Sair</span>
			</a>
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
	var collapseBtn = document.getElementById('appSidebarCollapse');
	var configToggle = document.getElementById('appSidebarConfigToggle');
	var configMenu = document.getElementById('appSidebarConfigMenu');

	function closeSidebar() {
		sidebar.classList.remove('open');
		overlay.classList.remove('visible');
	}
	toggle.addEventListener('click', function () {
		sidebar.classList.toggle('open');
		overlay.classList.toggle('visible');
	});
	overlay.addEventListener('click', closeSidebar);

	try {
		if (localStorage.getItem('gestorlog_sidebar_collapsed') === '1') {
			sidebar.classList.add('collapsed');
		}
	} catch (e) {}

	collapseBtn.addEventListener('click', function () {
		var collapsed = sidebar.classList.toggle('collapsed');
		try { localStorage.setItem('gestorlog_sidebar_collapsed', collapsed ? '1' : '0'); } catch (e) {}
	});

	configToggle.addEventListener('click', function () {
		configToggle.classList.toggle('open');
		configMenu.classList.toggle('open');
	});
})();
</script>
</body>
</html>
