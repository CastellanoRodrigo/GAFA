<?php #Llammo a pie 
include ("./Modelo/Conexion.php");
include("./Template/Cabecera.php");?>
<!--codigo aca-->

<script type="text/javascript">
			function ok(){
				swal.fire(  {title: "Detalle cargado!",
						icon: "success",
						showConfirmButton: true,
						showCancelButton: false,
						})
          }	
</script>
<script>
function ValidarFechas()
{
   var fechainicial = document.getElementById("FechaInicial").value;
   var fechafinal = document.getElementById("FechaFinal").value;

   if(Date.parse(fechafinal) < Date.parse(fechainicial)) {
   alert("La fecha de cierre debe ser mayor a la fecha inicial");
   $("input[type=date]").val("")
  }
}
</script>

<div class="centrado">
  <div class="centrado--titulo">
    <h1>ASIGNAR DETALLES PROYECTO DE SIEMBRA</h1>	
  </div>
  <div class="centrado--form">
    <form action="./agrDetalleSiembra.php" method="POST" class="centrado--form--form">
      <div class="labelInput">
          <label for="name">Nombre del proyecto:</label>
          <select name="cmbNombre" required>
          <option value="" selected disabled="cmbnombre">-SELECCIONE UNA-</option>
          <?php
          $consulta= "SELECT * FROM proyectos WHERE id_Proyecto NOT IN (SELECT id_Proyecto FROM detallesiembra) AND id_TipoProyecto = '2'";
          $ejecutar= mysqli_query($conexion, $consulta) or die(mysqli_error($datos_base));
          ?>
          <?php foreach ($ejecutar as $opciones): ?> 
          <option value="<?php echo $opciones['id_Proyecto']?>"><?php echo $opciones['nombreProyecto']?></option>
          <?php endforeach ?>
          </select>
      </div>
      <div class="labelInput">
          <label for="name">Fecha de inicio:</label>
          <input name="txtFechaInicio" type="date" class="form-control" id="FechaInicial" required/>
      </div>
      <div class="labelInput">
          <label for="name">Cultivo:</label>
          <select name="cmbCultivo" required>
          <option value="" selected disabled="cmbCultivos">-SELECCIONE UNA-</option>
          <?php
          $consulta= "SELECT * FROM Cultivos";
          $ejecutar= mysqli_query($conexion, $consulta) or die(mysqli_error($datos_base));
          ?>
          <?php foreach ($ejecutar as $opciones): ?> 
          <option value="<?php echo $opciones['id_Cultivo']?>"><?php echo $opciones['nombreCultivo']?></option>
          <?php endforeach ?>
          </select>
      </div>
      <div class="labelInput">
        <label for="name">Moneda:</label>
        <select name="cmbMoneda"  required>
            <option value="" selected disabled="cmbMoneda">-SELECCIONE UNA-</option>
            <?php
            $consulta= "SELECT * FROM moneda ORDER BY moneda ASC";
            $ejecutar= mysqli_query($conexion, $consulta) or die(mysqli_error($conexion));
            ?>
            <?php foreach ($ejecutar as $opciones): ?> 
            <option value="<?php echo $opciones['id_Moneda']?>"><?php echo $opciones['moneda']?></option>
            <?php endforeach ?>
          </select>
      </div>
      <div class="labelInput">
          <label for="name">Tipo de Cambio:</label>
          <input type="number" name="tipoCambio" min="1" id="prov" required>
      </div>
      <div class="labelInput">
          <label for="name">Inversion inicial:</label>
          <input name="txtInversion" type="number" min="1" required /> 
      </div>
      <div class="labelInput">
          <label for="name">Fecha de cierre aproximado:</label>
          <input name="txtFechaCierre" type="date" class="form-control" onchange="ValidarFechas()" id="FechaFinal" required />
      </div>
      <div class="labelInputbtn" style="gap:5px;">
        <input  type="reset" value="Cancelar" class="btn btn-danger">
        <input type="submit" value="Cargar detalle" class="btn btn-success"> 
      </div>
    </form>
    <?php if(isset($_GET['ok'])){ ?> <script>ok();</script><?php }?>
  </div>
</div>
