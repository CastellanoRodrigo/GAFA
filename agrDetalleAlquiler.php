<?php
include('./Modelo/Conexion.php');
include_once('./Modelo/Auth.php');
include_once('./Modelo/Alquileres.php');
requerirLogin();

function volverConError($conexion, string $codigo): void
{
    mysqli_close($conexion);
    header("Location: ./detalleAlquiler.php?error=" . $codigo);
    exit;
}

if (!tablasAlquilerListas($conexion)) {
    volverConError($conexion, 'tablas');
}

$idProyecto = (int)($_POST['cmbNombre'] ?? 0);
$inquilino = trim($_POST['inquilino'] ?? '');
$contacto = trim($_POST['contacto'] ?? '');
$inicio = $_POST['fechaInicio'] ?? '';
$fin = $_POST['fechaFin'] ?? '';
$canon = (float)str_replace(',', '.', $_POST['canon'] ?? '0');
$periodicidad = $_POST['periodicidad'] ?? '';
$idMoneda = (int)($_POST['cmbMoneda'] ?? 0);
$tipoCambioTxt = trim($_POST['tipoCambio'] ?? '');
$tipoCambio = $tipoCambioTxt === '' ? null : (float)str_replace(',', '.', $tipoCambioTxt);
$obs = trim($_POST['observaciones'] ?? '');

if ($inquilino === '' || !isset(PERIODICIDADES[$periodicidad]) || $idMoneda <= 0) {
    volverConError($conexion, 'datos');
}
if (!fechaValidaAlq($inicio) || !fechaValidaAlq($fin) || $fin < $inicio) {
    volverConError($conexion, 'fechas');
}
if ($canon <= 0) {
    volverConError($conexion, 'canon');
}

// El proyecto debe ser de tipo Alquiler (3) y no tener contrato todavia
$stmt = $conexion->prepare("SELECT p.id_Proyecto, (SELECT COUNT(*) FROM detallealquiler d WHERE d.id_Proyecto = p.id_Proyecto) AS tiene
                            FROM proyectos p WHERE p.id_Proyecto = ? AND p.id_TipoProyecto = 3");
$stmt->bind_param("i", $idProyecto);
$stmt->execute();
$proy = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$proy) {
    volverConError($conexion, 'proyecto');
}
if ((int)$proy['tiene'] > 0) {
    volverConError($conexion, 'duplicado');
}

$conexion->begin_transaction();
try {
    $stmt = $conexion->prepare("INSERT INTO detallealquiler
        (id_Proyecto, inquilino, contacto, fechaInicio, fechaFin, canon, periodicidad, id_Moneda, tipoCambio, observaciones)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $contactoDb = $contacto === '' ? null : $contacto;
    $obsDb = $obs === '' ? null : $obs;
    $stmt->bind_param("issssdsids", $idProyecto, $inquilino, $contactoDb, $inicio, $fin, $canon, $periodicidad, $idMoneda, $tipoCambio, $obsDb);
    $stmt->execute();
    $idContrato = $conexion->insert_id;
    $stmt->close();

    // Datos para avisos (solo si ya se ejecuto migracion_notificaciones.sql)
    $emailIn = trim($_POST['email'] ?? '');
    $telIn = trim($_POST['telefono'] ?? '');
    if (($emailIn !== '' || $telIn !== '') && columnaExiste($conexion, 'detallealquiler', 'email')) {
        $emailDb = ($emailIn !== '' && filter_var($emailIn, FILTER_VALIDATE_EMAIL)) ? $emailIn : null;
        $telDb = $telIn !== '' ? $telIn : null;
        $stmt = $conexion->prepare("UPDATE detallealquiler SET email = ?, telefono = ? WHERE id_DetalleAlquiler = ?");
        $stmt->bind_param("ssi", $emailDb, $telDb, $idContrato);
        $stmt->execute();
        $stmt->close();
    }

    // Cronograma de cobros: una cuota por periodo
    $stmt = $conexion->prepare("INSERT INTO cobrosalquiler (id_DetalleAlquiler, fechaVencimiento, monto) VALUES (?, ?, ?)");
    foreach (generarVencimientos($inicio, $fin, PERIODICIDADES[$periodicidad]) as $venc) {
        $stmt->bind_param("isd", $idContrato, $venc, $canon);
        $stmt->execute();
    }
    $stmt->close();
    $conexion->commit();
} catch (Throwable $e) {
    $conexion->rollback();
    volverConError($conexion, 'datos');
}

mysqli_close($conexion);
header("Location: ./cobrosAlquiler.php?no=" . $idContrato . "&ok");
exit;
