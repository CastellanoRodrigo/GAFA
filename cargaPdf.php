<?php
session_start();
include('./Modelo/Conexion.php');

$nroFactura=$_POST['nroFactura'];

if(file_exists($_FILES['fichero']['tmp_name'])){
    $url = './archivos/'.$nroFactura.".pdf";

    if(move_uploaded_file($_FILES['fichero']['tmp_name'], $url)){

        $nombre = "Compra";
        mysqli_query($conexion, "INSERT INTO archivos VALUES (DEFAULT, '$nombre', '".$url."')");
    }
}

header("Location: ./verCompras.php?ok");
mysqli_close($conexion);
?>