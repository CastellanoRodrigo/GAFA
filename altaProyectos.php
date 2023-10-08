<?php #Llammo a pie 
include ("./Modelo/Conexion.php");
include('./Template/Cabecera.php');?>

<script type="text/javascript">
    function ok(){
        swal.fire(  {title: "Proyecto cargado",
                icon: "success",
                showConfirmButton: true,
                showCancelButton: false,
                });
    }	
</script>

<div class="centrado">
    <div class="centrado--titulo">
    <h1>ALTA DE PROYECTO</h1>	
    </div>
    <div>
        <button class="btn btn-info" id="btnProyecto" style="color:white;">Cargar proyecto finalizado</button>
    </div>
    <div class="centrado--form" id="formu1">
        <form action="./agrProyecto.php" method="POST" class="centrado--form--form">
            <div><h4>Carga de proyecto Iniciado/No iniciado</h4></div>
          <div class="labelInput">
              <label for="name">Nombre del proyecto:</label>
              <input type="text" name="nombre1" id="prov" required>
          </div>
          <div class="labelInput">
              <label for="name">Parcela involucrada:</label>
              <select name="cmbparcela1" id="lista1" required>
                  <option value="" selected disabled="cmbparcela1">-SELECCIONE UNA-</option>
                  <?php
                  $consulta= "SELECT * FROM parcela";
                  $ejecutar= mysqli_query($conexion, $consulta) or die(mysqli_error($datos_base));
                  ?>
                  <?php foreach ($ejecutar as $opciones): ?> 
                  <option value="<?php echo $opciones['id_Parcela']?>"><?php echo $opciones['id_Parcela']?></option>
                  <?php endforeach ?>
              </select>
          </div>

          <div class="cantHas" id="select1lista"></div>

          <div class="labelInput">
              <label for="name">Estado del proyecto:</label>
              <select name="cmbEstado1"required>
                  <option value="" selected disabled="cmbestado1">-SELECCIONE UNA-</option>
                  <?php
                  $consulta= "SELECT * FROM estadoProyecto LIMIT 0,2";
                  $ejecutar= mysqli_query($conexion, $consulta) or die(mysqli_error($datos_base));
                  ?>
                  <?php foreach ($ejecutar as $opciones): ?> 
                  <option value="<?php echo $opciones['id_EstadoProyecto']?>"><?php echo $opciones['estado']?></option>
                  <?php endforeach ?>
              </select>
          </div>
<!--           <div class="labelInput">
              <label for="name">Cantidad de Hectareas:</label>
              <input type="number" name="nombre" id="prov" max="<?php echo $valor;?>" required>
          </div> -->
          <div class="labelInput">
              <label for="name">Tipo de proyecto:</label>
              <select name="cmbTipoProyecto1"required>
                  <option value="" selected disabled="cmbTipoProyecto1">-SELECCIONE UNA-</option>
                  <?php
                  $consulta= "SELECT * FROM tipoProyecto ORDER BY tipoProyecto ASC";
                  $ejecutar= mysqli_query($conexion, $consulta) or die(mysqli_error($datos_base));
                  ?>
                  <?php foreach ($ejecutar as $opciones): ?> 
                  <option value="<?php echo $opciones['id_tipoProyecto']?>"><?php echo $opciones['tipoProyecto']?></option>
                  <?php endforeach ?>
              </select>
          </div>
          <div class="labelInputbtn" style="gap:5px;">
<!--             <input  type="reset" value="Cancelar" class="btn btn-danger"> -->
            <input type="submit" value="Crear Proyecto" name="btn1" class="btn btn-success"> 
          </div>
        </form>
    </div>



    <div class="centrado--form" id="formu2">
        <form action="./agrProyecto.php" method="POST" class="centrado--form--form">
            <div><h4>Carga de proyecto Finalizado</h3></div>
          <div class="labelInput">
              <label for="name">Nombre del proyecto:</label>
              <input type="text" name="nombre2" id="prov" required>
          </div>
          <div class="labelInput">
              <label for="name">Parcela involucrada:</label>
              <select name="cmbparcela2" id="lista2" required>
                  <option value="" selected disabled="cmbParcelas2">-SELECCIONE UNA-</option>
                  <?php
                  $consulta= "SELECT * FROM parcela";
                  $ejecutar= mysqli_query($conexion, $consulta) or die(mysqli_error($datos_base));
                  ?>
                  <?php foreach ($ejecutar as $opciones): ?> 
                  <option value="<?php echo $opciones['id_Parcela']?>"><?php echo $opciones['id_Parcela']?></option>
                  <?php endforeach ?>
              </select>
          </div>

          <div class="cantHas" id="select2lista"></div>

          <div class="labelInput">
              <label for="name">Tipo de proyecto:</label>
              <select name="cmbTipoProyecto2"required>
                  <option value="" selected disabled="cmbTipoProyecto2">-SELECCIONE UNA-</option>
                  <?php
                  $consulta= "SELECT * FROM tipoProyecto ORDER BY tipoProyecto ASC";
                  $ejecutar= mysqli_query($conexion, $consulta) or die(mysqli_error($datos_base));
                  ?>
                  <?php foreach ($ejecutar as $opciones): ?> 
                  <option value="<?php echo $opciones['id_tipoProyecto']?>"><?php echo $opciones['tipoProyecto']?></option>
                  <?php endforeach ?>
              </select>
          </div>
          <div class="labelInputbtn" style="gap:5px;">

            <input type="submit" value="Crear Proyecto" name="btn2" class="btn btn-success"> 
          </div>
        </form>
    </div>
</div>

<?php if(isset($_GET['ok'])){ ?> <script>ok();</script><?php }?>

<script type="text/javascript">
        $(document).ready(function(){
            recargarLista();
            $('#lista1').change(function(){
                recargarLista();
            });
        })
    </script>
    <script type="text/javascript">
        function recargarLista(){
            $.ajax({
                type: "POST",
                url: "./datosCarga.php",
                data: "cmbparcela1=" + $('#lista1').val(),
                success:function(r){
                    $('#select1lista').html(r);
                }
            });
        }
    </script>

<script type="text/javascript">
        $(document).ready(function(){
            recargarLista2();
            $('#lista2').change(function(){
                recargarLista2();
            });
        })
    </script>
    <script type="text/javascript">
        function recargarLista2(){
            $.ajax({
                type: "POST",
                url: "./datosCarga2.php",
                data: "cmbparcela2=" + $('#lista2').val(),
                success:function(r){
                    $('#select2lista').html(r);
                }
            });
        }
    </script>




    <script src="./formu.js"></script>

<?php #Llammo a cabecera, incluye el archivo cabecera.php desde template
include('./Template/Pie.php');?>

