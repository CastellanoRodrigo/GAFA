<?php
session_start();
include ("./Modelo/Conexion.php");

if(isset($_POST['btn1'])){
    $parcela1 = $_POST['cmbparcela1'];
    $proyecto1 = $_POST['nombre1'];
    $tipo1 = $_POST['cmbTipoProyecto1'];
    $cantidad1 = $_POST['cantHas1'];
    $estado1 = $_POST['cmbEstado1'];

    mysqli_query($conexion, "INSERT INTO proyectos VALUES(DEFAULT, '$parcela1', '$proyecto1', '$tipo1', '$cantidad1', '$estado1')");
}

if(isset($_POST['btn2'])){
    $parcela2 = $_POST['cmbparcela2'];
    $proyecto2 = $_POST['nombre2'];
    $tipo2 = $_POST['cmbTipoProyecto2'];
    $cantidad2 = $_POST['cantHas2'];
    $estado2 = 3;/* Proyecto finalizado */

    mysqli_query($conexion, "INSERT INTO proyectos VALUES(DEFAULT, '$parcela2', '$proyecto2', '$tipo2', '$cantidad2', '$estado2')");
}

header("Location: ./altaProyectos.php?ok");
mysqli_close($conexion);

?>