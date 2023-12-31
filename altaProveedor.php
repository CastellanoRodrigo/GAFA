<?php #Llammo a cabecera, incluye el archivo cabecera.php desde template
include ("./Modelo/Conexion.php");
include('./Template/Cabecera.php');
?>

<div class="centrado">
    <div class="centrado-vlv">
        <a href="./proveedores.php"><button class="btn btn-success"><svg xmlns="http://www.w3.org/2000/svg" height="1em" viewBox="0 0 448 512"><!--! Font Awesome Free 6.4.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2023 Fonticons, Inc. --><style>svg{fill:#ffffff}</style><path d="M9.4 233.4c-12.5 12.5-12.5 32.8 0 45.3l160 160c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L109.2 288 416 288c17.7 0 32-14.3 32-32s-14.3-32-32-32l-306.7 0L214.6 118.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0l-160 160z"/></svg>&nbsp Atrás</button></a>
    </div>
    <div class="centrado--titulo">
        <h1>AGREGAR PROVEEDOR</h1>	
    </div>
    <div class="centrado--form">
        <form action="./agrProveedor.php" method="POST" class="centrado--form--form">
            <div>
                <label for="name">Nombre del proveedor:</label>
                <input type="text" name="prov" id="prov" required>
            </div>
            <div class="labelInputbtn">
                <button type="submit" class="btn btn-success">Agregar</button>
            </div>
        </form>
    </div>
</div>

</body>
<?php #Llammo a pie 
include('./template/pie.php');
?>