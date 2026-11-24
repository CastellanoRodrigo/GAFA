<?php
include("./Modelo/Conexion.php");
include_once('./Modelo/Auth.php');
requerirLogin();

$cmbparcela = (int)($_POST['cmbparcela2'] ?? 0);

$stmt = $conexion->prepare("SELECT p.id_Parcela, p.dimension FROM parcela p WHERE p.id_Parcela = ?");
$stmt->bind_param("i", $cmbparcela);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$dimension = (int)($row['dimension'] ?? 0);

echo "<div class='labelInput'><label>Hectareas disponibles: </label><p>" . $dimension . " Has</p></div>";
echo "
<div class='labelInput'>
    <label for='name'>Cantidad de Hectareas:</label>
    <input type='number' name='cantHas2' min='1' max='$dimension' required>
</div>
";
