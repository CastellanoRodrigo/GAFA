<?php
include('./Template/Cabecera.php');
include('./Modelo/Conexion.php');

error_reporting(0);

$consulta = Consultar($_GET['no']);

function Consultar($no_id)
{	
    include('./Modelo/Conexion.php');
	$sentencia =  "SELECT * FROM proyectos WHERE id_Proyecto='".$no_id."'";
	$resultado = mysqli_query($conexion, $sentencia);
	$filas = mysqli_fetch_assoc($resultado);
	return [
		$filas['id_Proyecto'],/*0*/
		$filas['id_Parcela'],/* 1 */
		$filas['nombreProyecto'],/* 2 */
		$filas['id_TipoProyecto'],/* 3 */
		$filas['cantidadHas'],/* 4 */
		$filas['id_EstadoProyecto'],/* 5 */
	];
}
$id = $consulta[0];
?>

<div class="centrado">
    <div class="centrado-vlv">
        <a href="./todosLosProyectos.php"><button class="btn btn-success"><svg xmlns="http://www.w3.org/2000/svg" height="1em" viewBox="0 0 448 512"><!--! Font Awesome Free 6.4.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2023 Fonticons, Inc. --><style>svg{fill:#ffffff}</style><path d="M9.4 233.4c-12.5 12.5-12.5 32.8 0 45.3l160 160c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L109.2 288 416 288c17.7 0 32-14.3 32-32s-14.3-32-32-32l-306.7 0L214.6 118.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0l-160 160z"/></svg>&nbsp Atrás</button></a>
    </div>
    <div class="centrado--titulo">
    <h1>DETALLE PROYECTO</h1>	
    </div>
    <?php 
        $sent= "SELECT estado FROM estadoproyecto WHERE id_EstadoProyecto = $consulta[5]";
        $resultado = $conexion->query($sent);
        $row = $resultado->fetch_assoc();
        $estado = $row['estado'];
    ?>
    <form class="centrado--form" action="./modProyecto.php" method="POST">
    <div class="centrado--form--info">
            <div>
                <h4>-Proyecto N°:</h4>
            </div>
            <div>
                <input type="number" name="id" class="readonly" readonly value="<?php echo $id;?>">
            </div>
        </div>


        <div class="centrado--form--info">
            <div>
                <h4>-Parcela:</h4>
            </div>
            <div>
                <p><?php echo $consulta[1];?></p>
            </div>
        </div>

        <div class="centrado--form--info">
            <div>
                <h4>-Nombre del Proyecto:</h4>
            </div>
            <div>
                <p><?php echo $consulta[2];?></p>
            </div>
        </div>

        <div class="centrado--form--info">
            <div>
                <h4>-Tipo de Proyecto:</h4>
            </div>
            <div>
                <p><?php 
                $sent= "SELECT tipoProyecto FROM tipoproyecto WHERE id_tipoProyecto = $consulta[3]";
                $resultado = $conexion->query($sent);
                $row = $resultado->fetch_assoc();
                $tipo = $row['tipoProyecto'];
                echo $tipo;?></p>
            </div>
        </div>

        <div class="centrado--form--info">
            <div>
                <h4>-Cantidad de Hectareas:</h4>
            </div>
            <div>
                <p><?php echo $consulta[4]." Has";?></p>
            </div>
        </div>








        <!-- VERIFICACIÓN TIPO DE PROYECTO -->

        <?php
            if($consulta[3] == 1){//Hacienda
                $sent= "SELECT dh.fechaInicio, dh.fechaCierre, dh.cantidadCabezas, ca.nombreCategoria, dh.inversion, m.moneda, dh.tipoCambio
                FROM detallehacienda dh
                INNER JOIN categoria ca ON ca.id_Categoria = dh.id_Categoria
                LEFT JOIN moneda m ON m.id_Moneda = dh.id_Moneda
                WHERE dh.id_Proyecto = $consulta[0]";
                $resultado = $conexion->query($sent);
                $row = $resultado->fetch_assoc();
                $fechaInicio = $row['fechaInicio'];
                $fechaCierre = $row['fechaCierre'];
                $cantidadCabezas = $row['cantidadCabezas'];
                $nombreCategoria = $row['nombreCategoria'];
                $inversion = $row['inversion'];
                $moneda = $row['moneda'];
                $tipoCambio = $row['tipoCambio'];
            ?>

        <div class="centrado--form--info">
            <div>
                <h4>-Fecha de inicio:</h4>
            </div>
            <div>
                <p><?php 
                    $fecIni = date("d-m-Y", strtotime($fechaInicio));
                    if($fecIni > "01-01-2000"){
                        echo $fecIni;
                    }
                    else{
                        echo "Sin asignar";
                    }
                ?></p>
            </div>
        </div>

        <div class="centrado--form--info">
            <div>
                <h4>-Fecha de cierre apróximado:</h4>
            </div>
            <div>
                <input type="date" name="fechaCierreH" value="<?php echo $fechaCierre; ?>">
            </div>
        </div>
        
        <div class="centrado--form--info">
            <div>
                <h4>-Cantidad de cabezas:</h4>
            </div>
            <div>
                <p><?php
                    if($cantidadCabezas > 0){
                        echo $cantidadCabezas;
                    }else{
                        echo "Sin asignar";
                    }
                ?></p>
            </div>
        </div>
       
        <div class="centrado--form--info">
            <div>
                <h4>-Categoría:</h4>
            </div>
            <div>
                <p><?php
                    if($nombreCategoria != ""){
                        echo $nombreCategoria;
                    }else{
                        echo "Sin asignar";
                    }
                ?></p>
            </div>
        </div>

        <div class="centrado--form--info">
            <div>
                <h4>-Inversión Inicial:</h4>
            </div>
            <div>
                <p><?php
                    if($inversion > 0){
                        $inv = number_format($inversion, 2, ',','.');
                        echo "$".$inv;
                    }else{
                        echo "Sin asignar";
                    }
                ?></p>
            </div>
        </div>

        <div class="centrado--form--info">
            <div>
                <h4>-Moneda:</h4>
            </div>
            <div>
                <p><?php
                    if($moneda != ""){
                        echo $moneda;
                    }else{
                        echo "Sin asignar";
                    } 
                ?></p>
            </div>
        </div>

        <div class="centrado--form--info">
            <div>
                <h4>-Tipo de cambio:</h4>
            </div>
            <div>
                <p><?php 
                    if($tipoCambio > 0){
                        $tipoCam = number_format($tipoCambio, 2, ',','.');
                        echo "$".$tipoCam;
                    }else{
                        echo "Sin asignar";
                    }
                ?></p>
            </div>
        </div>

            <?php }?>









        <?php
            if($consulta[3] == 2){//Siembra
                $sent= "SELECT d.fechaInicio, d.fechaCierre, d.inversion, c.nombreCultivo, m.moneda, d.tipoCambio
                FROM detallesiembra  d
                INNER JOIN cultivos c ON c.id_Cultivo = d.id_Cultivo
                LEFT JOIN moneda m ON m.id_Moneda = d.id_Moneda
                WHERE d.id_Proyecto = $consulta[0]";
                $resultado = $conexion->query($sent);
                $row = $resultado->fetch_assoc();
                $fechaInicioS = $row['fechaInicio'];
                $fechaCierreS = $row['fechaCierre'];
                $nombreCultivo = $row['nombreCultivo'];
                $inversionS = $row['inversion'];
                $monedaS = $row['moneda'];
                $tipoCambioS = $row['tipoCambio'];
        ?>

        <div class="centrado--form--info">
            <div>
                <h4>-Fecha de inicio:</h4>
            </div>
            <div>
                <p><?php 
                    $fecIni = date("d-m-Y", strtotime($fechaInicioS));
                    if($fecIni > "01-01-2000"){
                        echo $fecIni;
                    }
                    else{
                        echo "Sin asignar";
                    }
                ?></p>
            </div>
        </div>

        <div class="centrado--form--info">
            <div>
                <h4>-Fecha de cierre apróximado:</h4>
            </div>
            <div>
                <input type="date" name="fechaCierreS" value="<?php echo $fechaCierreS; ?>">
            </div>
        </div>

        <div class="centrado--form--info">
            <div>
                <h4>-Cultivo:</h4>
            </div>
            <div>
                <p><?php
                    if($nombreCultivo != ""){
                        echo $nombreCultivo;
                    }else{
                        echo "Sin asignar";
                    }
                ?></p>
            </div>
        </div>

        <div class="centrado--form--info">
            <div>
                <h4>-Inversión Inicial:</h4>
            </div>
            <div>
                <p><?php
                    if($inversionS > 0){
                        $inv = number_format($inversionS, 2, ',','.');
                        echo "$".$inv;
                    }else{
                        echo "Sin asignar";
                    }
                ?></p>
            </div>
        </div>

        <div class="centrado--form--info">
            <div>
                <h4>-Moneda:</h4>
            </div>
            <div>
                <p><?php
                    if($monedaS != ""){
                        echo $monedaS;
                    }else{
                        echo "Sin asignar";
                    }
                ?></p>
            </div>
        </div>

        <div class="centrado--form--info">
            <div>
                <h4>-Tipo de cambio:</h4>
            </div>
            <div>
                <p><?php
                    if($tipoCambioS > 0){
                        $tipoCamS = number_format($tipoCambioS, 2, ',','.');
                        echo "$".$tipoCamS;
                    }else{
                        echo "Sin asignar";
                    }
                ?></p>
            </div>
        </div>

        <?php }?>

        <?php $valorProyecto = $consulta[3]; ?>

        <div class="centrado--form--info">
            <div>
                <h4>-Estado:</h4>
            </div>
            <div>
                <select name="estado" required>
					<option selected value="100"><?php echo $estado?></option>
                    <?php
                    $consulta= "SELECT * FROM estadoproyecto ORDER BY estado";
                    $ejecutar= mysqli_query($conexion, $consulta) or die(mysqli_error($conexion));
                    ?>
                    <?php foreach ($ejecutar as $opciones): ?> 
                    <option value="<?php echo $opciones['id_EstadoProyecto']?>"><?php echo $opciones['estado']?></option>
                    <?php endforeach ?>
                </select>
            </div>
        </div>
        <div class="labelInputbtn" style="gap:5px;">
            <button type="submit" class="btn btn-success">Modificar</button>
        </div>
        <hr style="border: 1px solid black;width:100%;height:1px;"/>

<?php       
if($valorProyecto == 1){//Hacienda
    $sent= "SELECT dh.inversion, m.moneda, dh.tipoCambio
    FROM detallehacienda dh
    LEFT JOIN moneda m ON m.id_Moneda = dh.id_Moneda
    WHERE dh.id_Proyecto = '$id'";
    $resultado = $conexion->query($sent);
    $row = $resultado->fetch_assoc();
    $inversion = $row['inversion'];
    $moneda = $row['moneda'];
    $tipoCambio = $row['tipoCambio'];

    $invInicial = $inversion;

    if($moneda == "USD"){
        $invInicialDolares = $invInicial;
        $invInicialPesos = $invInicial * $tipoCambio;
    }

    if($moneda == "ARS"){
        $invInicialPesos = $invInicial;
        $invInicialDolares = $invInicial / $tipoCambio;
    }

    $enPesos = 0;
    $enDolares = 0;

    $consulta2=mysqli_query($conexion, "SELECT c.importeTotal, c.id_Moneda, c.tipoCambio
    FROM compras c
    LEFT JOIN compraproyecto co ON co.id_Compras = c.id_Compras
    WHERE co.id_Proyecto = '$id'");
    while($listar2 = mysqli_fetch_array($consulta2)){
        
        //PARA SACAR IMPORTE TOTAL EN DOLARES
        if($listar2['id_Moneda'] == 1){
            $enPesos = $enPesos + $listar2['importeTotal'];
            $enDolares = $enDolares + ($listar2['importeTotal'] / $listar2['tipoCambio']);
        }
        
        //PARA SACAR IMPORTE TOTAL EN PESOS
        if($listar2['id_Moneda'] == 2){
            $enDolares = $enDolares + $listar2['importeTotal'];
            $enPesos = $enPesos + ($listar2['importeTotal'] * $listar2['tipoCambio']);
        }
    }
    $enDolares = $enDolares + $invInicialDolares;
    $enPesos = $enPesos + $invInicialPesos;

    $dolares = number_format($enDolares, 2, ',','.');
    $pesos = number_format($enPesos, 2, ',','.');
    ?>
        <div class="centrado--form--info">
            <div>
                <h3>Total inversión en USD</h3>
            </div>
            <div>
                <h4 style="color:green;"><?php echo "$".$dolares;?></h4>
            </div>
        </div>
        <div class="centrado--form--info">
            <div>
                <h3>Total inversión en ARS</h3>
            </div>
            <div>
            <h4 style="color:green;"><?php echo "$".$pesos;?></h4>
            </div>
        </div>
        <?php }?>



        <!-- SIEMBRA -->
        <?php       
if($valorProyecto == 2){//Siembra
    $sent= "SELECT ds.inversion, m.moneda, ds.tipoCambio
    FROM detallesiembra ds
    LEFT JOIN moneda m ON m.id_Moneda = ds.id_Moneda
    WHERE ds.id_Proyecto = '$id'";
    $resultado = $conexion->query($sent);
    $row = $resultado->fetch_assoc();
    $inversion = $row['inversion'];
    $moneda = $row['moneda'];
    $tipoCambio = $row['tipoCambio'];

    $invInicial = $inversion;

    if($moneda == "USD"){
        $invInicialDolares = $invInicial;
        $invInicialPesos = $invInicial * $tipoCambio;
    }

    if($moneda == "ARS"){
        $invInicialPesos = $invInicial;
        $invInicialDolares = $invInicial / $tipoCambio;
    }

    $enPesos = 0;
    $enDolares = 0;

    $consulta2=mysqli_query($conexion, "SELECT c.importeTotal, c.id_Moneda, c.tipoCambio
    FROM compras c
    LEFT JOIN compraproyecto co ON co.id_Compras = c.id_Compras
    WHERE co.id_Proyecto = '$id'");
    while($listar2 = mysqli_fetch_array($consulta2)){
        
        //PARA SACAR IMPORTE TOTAL EN DOLARES
        if($listar2['id_Moneda'] == 1){
            $enPesos = $enPesos + $listar2['importeTotal'];
            $enDolares = $enDolares + ($listar2['importeTotal'] / $listar2['tipoCambio']);
        }
        
        //PARA SACAR IMPORTE TOTAL EN PESOS
        if($listar2['id_Moneda'] == 2){
            $enDolares = $enDolares + $listar2['importeTotal'];
            $enPesos = $enPesos + ($listar2['importeTotal'] * $listar2['tipoCambio']);
        }
    }
    $enDolares = $enDolares + $invInicialDolares;
    $enPesos = $enPesos + $invInicialPesos;

    $dolares = number_format($enDolares, 2, ',','.');
    $pesos = number_format($enPesos, 2, ',','.');
    ?>
        <div class="centrado--form--info">
            <div>
                <h3>Total inversión en USD</h3>
            </div>
            <div>
                <h4 style="color:green;"><?php echo "$".$dolares;?></h4>
            </div>
        </div>
        <div class="centrado--form--info">
            <div>
                <h3>Total inversión en ARS</h3>
            </div>
            <div>
            <h4 style="color:green;"><?php echo "$".$pesos;?></h4>
            </div>
        </div>
        <?php }?>

        <p>*Cotizacion divisa venta Banco Nacion</p>
    </form>

    <h3>Compras</h3>
    <?php
    $total = mysqli_num_rows(mysqli_query($conexion,"SELECT id_CompraProyecto FROM compraproyecto WHERE id_Proyecto = '$id'"));
    if($total==0){
        echo "<p>No hay compras asignadas a este proyecto</p>";
    }else{
    ?>
        <table style="border:none; width:1200px; background-color: white; border-radius:2px;box-shadow: 5px 5px 5px 5px rgb(148, 148, 148);">
            <thead>
		<tr style="color:green; background-color:white;font-weight:bold;border-bottom: 2px solid green;">			
			<th style="text-align:center;font-size:16pt;text-align:right;padding-right:5px;border-right: 2px solid green">Detalle</th>
			<th style="text-align:center;font-size:16pt;text-align:right;padding-right:5px;border-right: 2px solid green">Fecha</th>
            <th style="text-align:center;font-size:16pt;text-align:right;padding-right:5px;border-right: 2px solid green">Moneda</th>
			<th style="text-align:center;font-size:16pt;text-align:right;padding-right:5px;">Importe Total</th>				
		</tr>
		</thead>
	   <?php
		$consulta=mysqli_query($conexion, "SELECT cp.fecha, cp.importeTotal, cp.detalle, m.moneda
		FROM compraproyecto c
		LEFT JOIN compras cp ON cp.id_Compras = c.id_Compras
        LEFT JOIN moneda m ON m.id_Moneda = cp.id_Moneda
		WHERE c.id_Proyecto = '$id'");
		while($listar = mysqli_fetch_array($consulta))
		{
            $fecIni = date("d-m-Y", strtotime($listar['fecha']));
			echo "
			<tr>
            <td style='font-weight:bold;text-align:right;padding-right:5px;border-right: 1px solid green'>$listar[detalle]</td>
            <td style='font-weight:bold;text-align:right;padding-right:5px;border-right: 1px solid green'>$fecIni</td>
            <td style='font-weight:bold;text-align:right;padding-right:5px;border-right: 1px solid green'>$listar[moneda]</td>
            <td style='font-weight:bold;text-align:right;padding-right:5px;'>$$listar[importeTotal]</td>
            </tr>";
		} 
        ?>
        </table>
        <?php
    }
        ?>

</div>

</body>