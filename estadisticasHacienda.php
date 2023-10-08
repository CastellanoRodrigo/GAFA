<?php #Llammo a pie 
include('./Template/Cabecera.php');
include('./Modelo/Conexion.php');
?>

<html>
  <head>
  <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">
      google.charts.load('current', {'packages':['corechart']});
      google.charts.setOnLoadCallback(drawChart1);
      
      function drawChart1() {

      var data1 = google.visualization.arrayToDataTable([
        ['Task', ''],
        <?php    
        $SQL = "SELECT c.nombreCategoria, COUNT(d.id_DetalleHacienda) AS total
        FROM detallehacienda d
        LEFT JOIN categoria c ON c.id_Categoria = d.id_Categoria
        GROUP BY d.id_Categoria
        ORDER BY c.nombreCategoria";          

        $consulta = mysqli_query($conexion, $SQL);
        while ($resultado = mysqli_fetch_assoc($consulta)){
          echo "['Categoria: " .$resultado['nombreCategoria']."', " .$resultado['total']."],";
        }

      ?>
      ]);
      /* rgb(220, 57, 18)  ROJO
      rgb(16, 150, 24) VERDE
      rgb(255, 153, 0) AMARILLO      
      */
      var options1 = {
        title: 'Proyectos de Hacienda por Categoria'
      };

      var chart1 = new google.visualization.ColumnChart(document.getElementById('piechart1'));
      chart1.draw(data1, options1);
      }
    </script>
  </head>
  <div class="centrado">
        <div class="centrado--titulo">
            <h1>ESTADÍSTICAS HACIENDA</h1>		
        </div>
        <div class="centrado--stats">
            <div class="centrado--stats--cont">
              <div class="centrado--stats--cont--detail particular">
                  <h2><u>Proyectos</u></h2>
                  <?php
                    $sent= "SELECT COUNT(*) AS TOTAL FROM proyectos WHERE id_TipoProyecto = 1 AND id_EstadoProyecto = 1";
                    $resultado = $conexion->query($sent);
                    $row = $resultado->fetch_assoc();
                    $totalNo = $row['TOTAL'];

                    $sent= "SELECT COUNT(*) AS TOTAL FROM proyectos WHERE id_TipoProyecto = 1 AND id_EstadoProyecto = 2";
                    $resultado = $conexion->query($sent);
                    $row = $resultado->fetch_assoc();
                    $totalIn = $row['TOTAL'];

                    $sent= "SELECT COUNT(*) AS TOTAL FROM proyectos WHERE id_TipoProyecto = 1 AND id_EstadoProyecto = 3";
                    $resultado = $conexion->query($sent);
                    $row = $resultado->fetch_assoc();
                    $totalFi = $row['TOTAL'];

                    $tot = $totalFi + $totalIn + $totalNo;
                  ?>      
                    <div class="labelInput">
                      <h4>Activos:</h4>
                      <h4><?php echo $totalIn; ?></h4>
                    </div>
                    <div class="labelInput">
                      <h4>Por comenzar:</h4>
                      <h4><?php echo $totalNo; ?></h4>
                    </div>
                    <div class="labelInput">
                      <h4>Finalizados:</h4>
                      <h4><?php echo $totalFi; ?></h4>
                    </div>
                    <hr style="border: 1px solid black;width:100%;height:1px;"/>
                    <div class="labelInput">
                      <h2 style="color:green;">Totales:</h2>
                      <h2 style="color:green;"><?php echo $tot; ?></h2>
                    </div>


                  </div>
                <div class="centrado--stats--cont--detail" id="piechart1"></div>
            </div>
        </div>
    </div>
  </body>
</html>
<?php #Llammo a pie 
include('./Template/Pie.php');
?>