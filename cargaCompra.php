<?php
include("./Modelo/Conexion.php");
include_once('./Modelo/Auth.php');
requerirEscritura($conexion);

// Captura los datos ingresados en el formulario
$fecha = $_POST['fecha'] ?? '';
$factura = $_POST['cmbTipoFactura'] ?? '';
$puntoVenta = $_POST['ptoVenta'] ?? '';
$nroFactura = $_POST['nroFactura'] ?? '';
$proveedor = $_POST['prov'] ?? '';
$tipoDoc = $_POST['cmbTipoDoc'] ?? '';
$dni = $_POST['dni'] ?? '';
$tipoCambio = $_POST['tipoCambio'] ?? '';
$moneda = $_POST['cmbMoneda'] ?? '';
$importeNeto = $_POST['impNeto'] ?? '';
$iva = $_POST['iva'] ?? '';
$importeTotal = $_POST['impTotal'] ?? '';
$detalle = $_POST['detalle'] ?? '';
$formaPago = $_POST['cmbFormaPago'] ?? '';

$stmt = $conexion->prepare("INSERT INTO compras VALUES (DEFAULT, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssssssssssss", $fecha, $factura, $puntoVenta, $nroFactura, $tipoDoc, $dni, $proveedor,
    $tipoCambio, $moneda, $importeNeto, $iva, $importeTotal, $detalle, $formaPago);
$stmt->execute();
$idCompra = $conexion->insert_id;
$stmt->close();

if (isset($_POST['nombreProyecto'])) {
    $idProyecto = (int)$_POST['nombreProyecto'];
    $stmt = $conexion->prepare("INSERT INTO compraproyecto VALUES (DEFAULT, ?, ?, 2)");
    $stmt->bind_param("ii", $idCompra, $idProyecto);
} else {
    $stmt = $conexion->prepare("INSERT INTO compraproyecto VALUES (DEFAULT, ?, 0, 1)");
    $stmt->bind_param("i", $idCompra);
}
$stmt->execute();
$stmt->close();

/* SUBIR ARCHIVO (solo PDF, nombre saneado) */
$nroSeguro = preg_replace('/[^A-Za-z0-9_-]/', '', $nroFactura);
if ($nroSeguro !== '' && isset($_FILES['fichero']) && $_FILES['fichero']['error'] === UPLOAD_ERR_OK) {
    $tmp = $_FILES['fichero']['tmp_name'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $ext = strtolower(pathinfo($_FILES['fichero']['name'], PATHINFO_EXTENSION));
    if ($mime === 'application/pdf' && $ext === 'pdf') {
        $url = './archivos/' . $nroSeguro . ".pdf";
        if (move_uploaded_file($tmp, $url)) {
            $nombre = "Compra";
            $stmt = $conexion->prepare("INSERT INTO archivos VALUES (DEFAULT, ?, ?)");
            $stmt->bind_param("ss", $nombre, $url);
            $stmt->execute();
            $stmt->close();
        }
    }
}

mysqli_close($conexion);
header("Location: ./altaCompras.php?ok");
exit;
