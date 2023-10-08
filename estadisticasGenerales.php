<?php #Llammo a pie 
include('./Template/Cabecera.php');
include('./Modelo/Conexion.php');
?>

<html>
  <head>
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">
      google.charts.load('current', {'packages':['corechart']});
      google.charts.setOnLoadCallback(drawChart);
      google.charts.setOnLoadCallback(drawChart1);
      google.charts.setOnLoadCallback(drawChart2);
      google.charts.setOnLoadCallback(drawChart3);
      google.charts.setOnLoadCallback(drawChart4);

      function drawChart() {

        var data = google.visualization.arrayToDataTable([
          ['Task', 'Hours per Day'],
<?php    
          $SQL = "SELECT * FROM detallesiembra ds
                  INNER jOIN proyectos p ON p.Id_Proyecto = ds.Id_Proyecto";          
     
          $consulta = mysqli_query($conexion, $SQL);
          while ($resultado = mysqli_fetch_assoc($consulta)){
            echo "['" .$resultado['nombreProyecto']."', " .$resultado['cantidadHas']."],";
          }

?>
        ]);

        var options = {
          title: 'Estadisticas de Rinde Especulado - Deberia ser RINDE OBTENIDO '
        };

        var chart = new google.visualization.PieChart(document.getElementById('piechart'));
        chart.draw(data, options);
      }




      function drawChart1() {

      var data1 = google.visualization.arrayToDataTable([
        ['Task', ''],
      <?php    
        $SQL = "SELECT * FROM detallesiembra ds
                INNER jOIN proyectos p ON p.Id_Proyecto = ds.Id_Proyecto";          

        $consulta = mysqli_query($conexion, $SQL);
        while ($resultado = mysqli_fetch_assoc($consulta)){
          echo "['" .$resultado['nombreProyecto']."', " .$resultado['cantidadHas']."],";
        }

      ?>
      ]);

      var options1 = {
        title: 'Hectareas por proyecto'
      };

      var chart1 = new google.visualization.AreaChart(document.getElementById('piechart1'));
      chart1.draw(data1, options1);
      }




      function drawChart2() {

      var data2 = google.visualization.arrayToDataTable([
      <?php    
          $sent= "SELECT COUNT(*) AS TOTAL FROM proyectos WHERE id_TipoProyecto = 1";
          $resultado = $conexion->query($sent);
          $row = $resultado->fetch_assoc();
          $totHacienda = $row['TOTAL'];        

          $sent= "SELECT COUNT(*) AS TOTAL1 FROM proyectos WHERE id_TipoProyecto = 2";
          $resultado = $conexion->query($sent);
          $row = $resultado->fetch_assoc();
          $totSiembra = $row['TOTAL1'];        
          ?>
          ['', 'Total', { role: "style" } ],
          ['Hacienda', <?php echo $totHacienda;?>, 'rgb(16, 150, 24)'],
          ['Siembra', <?php echo $totSiembra;?>, "rgb(220, 57, 18)"],
      ]);

      var options2 = {
        title: 'Cantidad de proyectos Hacienda/Siembra'
      };

      var chart2 = new google.visualization.ColumnChart(document.getElementById('piechart2'));
      chart2.draw(data2, options2);
      }





      function drawChart3() {

      var data3 = google.visualization.arrayToDataTable([
        ['Task', ''],
        <?php    
        $SQL = "SELECT id_Parcela, COUNT(id_Proyecto) AS total
        FROM proyectos
        GROUP BY id_Parcela
        ORDER BY id_Parcela";          

        $consulta = mysqli_query($conexion, $SQL);
        while ($resultado = mysqli_fetch_assoc($consulta)){
          echo "['Parcela N°" .$resultado['id_Parcela']."', " .$resultado['total']."],";
        }

      ?>
      ]);
      /* rgb(220, 57, 18)  ROJO
      rgb(16, 150, 24) VERDE
      rgb(255, 153, 0) AMARILLO      
      */
      var options3 = {
        title: 'Cantidad de proyectos por Parcela'
      };

      var chart3 = new google.visualization.ColumnChart(document.getElementById('piechart3'));
      chart3.draw(data3, options3);
      }



      
      function drawChart4() {

      var data4 = google.visualization.arrayToDataTable([
        ['Task', ''],
        <?php    
        $SQL = "SELECT c.nombreCultivo, COUNT(d.id_DetalleSiembra) AS total
        FROM detallesiembra d
        LEFT JOIN cultivos c ON c.id_Cultivo = d.id_Cultivo
        GROUP BY d.id_Cultivo
        ORDER BY c.nombreCultivo";          

        $consulta = mysqli_query($conexion, $SQL);
        while ($resultado = mysqli_fetch_assoc($consulta)){
          echo "['Cultivo: " .$resultado['nombreCultivo']."', " .$resultado['total']."],";
        }

      ?>
      ]);
      /* rgb(220, 57, 18)  ROJO
      rgb(16, 150, 24) VERDE
      rgb(255, 153, 0) AMARILLO      
      */
      var options4 = {
        title: 'Proyectos de Siembra por Tipo de Cultivo'
      };

      var chart4 = new google.visualization.ColumnChart(document.getElementById('piechart4'));
      chart4.draw(data4, options4);
      }
    </script>
  </head>
    <div class="centrado">
      <div class="centrado--titulo">
        <h1>ESTADÍSTICAS GENERALES</h1>		
      </div>
      <div class="centrado--stats">

        <div class="centrado--stats--cont">
            <div class="centrado--stats--cont--detail" id="piechart"></div>
            <div class="centrado--stats--cont--detail" id="piechart1"></div>
        </div>

        <div class="centrado--stats--cont">
            <div class="centrado--stats--cont--detail" id="piechart2"></div>
            <div class="centrado--stats--cont--detail" id="piechart3"></div>
        </div>

      </div>
    </div>
    
  </body>
</html>
<?php #Llammo a pie 
include('./Template/Pie.php');
?>