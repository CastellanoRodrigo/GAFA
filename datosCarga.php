<?php
include("./Modelo/Conexion.php");
include('./Modelo/Auth.php');
requerirLogin();

$cmbparcela = (int)($_POST['cmbparcela1'] ?? 0);

$stmt = $conexion->prepare("SELECT p.id_Parcela, p.dimension, SUM(pr.cantidadHas) AS total, pr.id_EstadoProyecto
FROM parcela p
LEFT JOIN proyectos pr ON pr.id_Parcela = p.id_Parcela
WHERE p.id_Parcela = ? AND pr.id_EstadoProyecto = 2");
$stmt->bind_param("i", $cmbparcela);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$dimension = (int)($row['dimension'] ?? 0);
$total = (int)($row['total'] ?? 0);
$dispon = $dimension - $total;

echo "<div class='labelInput'><label>Hectareas disponibles: </label><p>" . $dispon . " Has</p></div>";
echo "
<div class='labelInput'>
    <label for='name'>Cantidad de Hectareas:</label>
    <input type='number' name='cantHas1' min='1' max='$dispon' required>
</div>
";
