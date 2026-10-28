<?php
require_once('../../Connections/site.php');

//$iduser = $_SESSION['iduser'];
$palavra = $_REQUEST['palavra'];
$ip = $_REQUEST['ipusuariolog'];
$data1 = $_REQUEST['data1'];
$data2 = $_REQUEST['data2'];

// definir o numero de itens por pagina
$itens_por_pagina = 50;

// pegar a pagina atual
$pagina_atual = intval($_GET['pagina_limite']);
if ($pagina_atual <= 0)
    $pagina_atual = 1;

if ($data1 != "" || $data2 != "") {
    $_SESSION['data1'] = $data1;
    $_SESSION['data2'] = $data2;
}
$data1 = $_SESSION['data1'];
$data2 = $_SESSION['data2'];

if ($_POST) {
    $_SESSION['palavra'] = $palavra;
}
$palavra = $_SESSION['palavra'];

$_SESSION['pagina_limite'] = $pagina_atual;
$pagina_limite = $pagina_atual;

// Sem filtro de data definido, limita aos últimos 7 dias por padrão
// (evita varrer a tabela inteira, que tem milhões de linhas)
if ($data1 == '') {
    $data1 = date('Y-m-d', strtotime('-7 days'));
}
if ($data2 == '') {
    $data2 = date('Y-m-d');
}

$offset = ($pagina_atual - 1) * $itens_por_pagina;

$querybuscalog = $pdo->prepare("SELECT * FROM `SystemEvents` WHERE `Message` LIKE :palavra AND `Message` LIKE :ip AND `ReceivedAt` >= :data1 AND `ReceivedAt` <= :data2 ORDER BY `ID` DESC LIMIT :offset, :itens_por_pagina");
$querybuscalog->bindValue(':palavra', "%$palavra%");
$querybuscalog->bindValue(':ip', "%$ip%");
$querybuscalog->bindValue(':data1', "$data1 00:00:00");
$querybuscalog->bindValue(':data2', "$data2 23:59:59");
$querybuscalog->bindValue(':offset', $offset, PDO::PARAM_INT);
$querybuscalog->bindValue(':itens_por_pagina', $itens_por_pagina, PDO::PARAM_INT);
$querybuscalog->execute();

$sqlselecionausuariolog = "SELECT * FROM `usuario_log` ";
$queryselecionausuariolog = $pdo->query($sqlselecionausuariolog);

?>
<script src="exportar_excel.js"></script>

<div class="container">
    <div class="row clearfix">
        <div class="col-md-12 column">

            <span class="label label-default">Exportar dados</span>
            <p>Clique no botão abaixo para gerar o arquivo .xls do Excel</p>
            <input type="button" onclick="tableToExcel('testTable', 'W3C Example Table')" value="Exportar e fazer download">
        </div>
    </div>

    <div class="row clearfix">
        <div class="col-md-12 column">

            <span class="label label-default">Relatorio de acesso</span>
            <form action="?pagina=relatorio" method="POST">

                <table border="0" align="center">

                    <tbody>
                        <tr>
                            <td>Usuario:</td>
                            <td>
                                <select name="ipusuariolog" id="usuariolog">

                                    <?php
                                    echo '<option value="">Todos</option>';
                                    while ($row_rsselecionausuariolog = $queryselecionausuariolog->fetch(PDO::FETCH_ASSOC)) {

                                        echo '<option value="' . $row_rsselecionausuariolog['ipusuariolog'] . '">' . $row_rsselecionausuariolog['usuariolog'] . '</option>';
                                    }
                                    ?>



                                </select></td>

                            <td>De:</td>
                            <td><input type="date" name="data1" value="<?php echo $data1; ?>"></td>
                            <td>Até:</td>
                            <td><input type="date" name="data2" value="<?php echo $data2; ?>"></td>
                            <td><input type="text" name="palavra" value="<?php echo $palavra; ?>" /></td>
                            <td><input type="submit" value="buscar" /></td>
                        </tr>
                    </tbody>
                </table>
            </form>

            <table class="table" id="testTable">

                <thead>

                    <tr>
                        <th>
                            Data/hora Recebida
                        </th>
                        <th>
                            Data/hora Reportada
                        </th>
                        <th>
                            Acesso
                        </th>
                        <th>
                            Servidor
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    while ($row_rsbuscalog = $querybuscalog->fetch(PDO::FETCH_ASSOC)) {

                        ?>
                        <tr>
                            <td>
                                <?php echo $row_rsbuscalog['ReceivedAt'] ?>
                            </td>
                            <td>
                                <?php echo $row_rsbuscalog['DeviceReportedTime'] ?>
                            </td>
                            <td>
                                <?php echo $row_rsbuscalog['Message'] ?>
                            </td>
                            <td>
                                <?php echo $row_rsbuscalog['FromHost'] ?>
                            </td>
                        </tr>
                        <?php


                    }
                    ?>




                </tbody>
            </table>
            <ul class="pagination">
                <?php if ($pagina_limite > 1): ?>
                <li>
                    <a href="?pagina=relatorio&pagina_limite=<?php echo $pagina_limite - 1; ?>">Anterior</a>
                </li>
                <?php endif; ?>
                <li><a href="#" style="pointer-events:none;">Página <?php echo $pagina_limite; ?></a></li>
                <!--
                                <li>
                                    <a href="#">1</a>
                                </li>
                                <li>
                                    <a href="#">2</a>
                                </li>
                                <li>
                                    <a href="#">3</a>
                                </li>
                                <li>
                                    <a href="#">4</a>
                                </li>
                                <li>
                                    <a href="#">5</a>
                                </li>
                -->
                <li>
                    <a href="?pagina=relatorio&pagina_limite=<?php echo $pagina_limite + 1; ?>">Proximo</a>
                </li>
            </ul>
        </div>
    </div>
</div>
