<?php #Llammo a pie 
include('./Template/Cabecera.php');
include('./Modelo/Conexion.php');
?>

<script>
    window.onload = function() {
      var general = document.getElementById('general');
      var nombreProyecto = document.getElementById('nombreProyecto');
      
      general.addEventListener('change', function() {
        if(general.checked == true){
            nombreProyecto.disabled = true;
        }else{
            nombreProyecto.disabled = false;
        }
        /* general.checked = false; */
      });

    }
  </script>

<div class="centrado">
    <div class="centrado--titulo">
    <h1>CARGAR COMPRA</h1>
    </div>
    <div class="centrado--form">
        <form action="./cargaCompra.php" method="POST" class="centrado--form--form" enctype="multipart/form-data">
            <div class="labelInput">
                <label for="name">Compra General:</label>
                <input type="checkbox" id="general" name="general"/>
            </div>

            <hr style="width:100%;">

            <div class="labelInput">
                <label for="name">Asignar al Proyecto:</label>
                <select id="nombreProyecto" name="nombreProyecto" required>
                    <option value="" selected disabled="nombreProyecto">-SELECCIONE UNA-</option>
                    <?php
                    $consulta= "SELECT * FROM Proyectos ORDER BY nombreProyecto ASC";
                    $ejecutar= mysqli_query($conexion, $consulta) or die(mysqli_error($datos_base));
                    ?>
                    <?php foreach ($ejecutar as $opciones): ?> 
                    <option value="<?php echo $opciones['id_Proyecto']?>"><?php echo $opciones['nombreProyecto']?></option>
                    <?php endforeach ?>
                </select>  
            </div>
            <div class="labelInput">
                <label for="name">Fecha:</label>
                <input type="date" name="fecha"  id="prov" class="form-control" required>
            </div>
            <div class="labelInput">
                <label for="name">Tipo de factura:</label>
                <select  name="cmbTipoFactura"  required> 
                  <option  value="" disabled selected >-SELECCIONE UNA-</option>
                  <option  value="A">A</option>
                  <option value="B" >B</option>
                  <option value="C" >C</option>
                  <option value="Otro" >Otro</option>
                </select>
            </div>
            <div class="labelInput">
                <label for="name">Punto de venta:</label>
                <input type="text" name="ptoVenta" id="prov" required>
            </div>
            <div class="labelInput">
                <label for="name">Nro de Factura:</label>
                <input type="text" name="nroFactura" id="prov" required>
            </div>
            <div class="labelInput">
              <label>Proveedor:</label>
              <select name="prov"  required>
                  <option value="" selected disabled="tipo">-SELECCIONE UNA-</option>
                  <?php
                  $consulta= "SELECT * FROM proveedores ORDER BY proveedor ASC";
                  $ejecutar= mysqli_query($conexion, $consulta) or die(mysqli_error($conexion));
                  ?>
                  <?php foreach ($ejecutar as $opciones): ?> 
                  <option value="<?php echo $opciones['id_Proveedor']?>"><?php echo $opciones['proveedor']?></option>
                  <?php endforeach ?>
                </select>
            </div>
            <div class="labelInput">
                <label for="name">Tipo de Documento del emisor:</label>
                <select  name="cmbTipoDoc"  required> 
                  <option  value="" disabled selected >-SELECCIONE UNA-</option>
                  <option  value="CUIT">CUIT</option>
                  <option value="CUIL" >CUIL</option>
                  <option value="DNI" >DNI</option>
                </select>
            </div>
            <div class="labelInput">
                <label for="name">Nro de Documento del Emisor:</label>
                <input type="number" name="dni" min="1" id="prov" required>
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
                <label for="name">Importe Neto:</label>
                <input type="number" name="impNeto" min="1" id="prov" required>
            </div>
            <div class="labelInput">
                <label for="name">IVA:</label>
                <input type="number" name="iva" min="1" id="prov" required>
            </div>
            <div class="labelInput">
                <label for="name">Importe Total:</label>
                <input type="number" name="impTotal" min="1" id="prov" required>
            </div>
            <div class="labelInput">
              <label for="name">Detalle:</label>
              <textarea name="detalle" id="" cols="30" rows="3" required></textarea>
            </div>
            <div class="labelInput">
              <label>Forma de Pago:</label>
              <select name="cmbFormaPago"  required>
                  <option value="" selected disabled="cmbFormaPago">-SELECCIONE UNA-</option>
                  <?php
                  $consulta= "SELECT * FROM formapago ORDER BY formaPago ASC";
                  $ejecutar= mysqli_query($conexion, $consulta) or die(mysqli_error($conexion));
                  ?>
                  <?php foreach ($ejecutar as $opciones): ?> 
                  <option value="<?php echo $opciones['id_Forma']?>"><?php echo $opciones['formaPago']?></option>
                  <?php endforeach ?>
                </select>
            </div>
            <div class="labelInput">
                <label for="name">Subir Archivo:</label>
                <input type="file" name="fichero" size="150" id="archivo" accept="application/pdf">
            </div>

            <div class="labelInputbtn">
                <button type="submit" class="btn btn-success">Agregar</button>
            </div>
        </form>
    </div>
</div>

<?php #Llammo a pie 
include('./Template/Pie.php');
?>