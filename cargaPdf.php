<?php
session_start();
if (empty($_SESSION['usuario'])) {
    header("Location: ./login.php");
    exit;
}
include('./Modelo/Conexion.php');

// Solo caracteres seguros en el nombre del archivo (evita path traversal)
$nroFactura = preg_replace('/[^A-Za-z0-9_-]/', '', $_POST['nroFactura'] ?? '');

if ($nroFactura !== '' && isset($_FILES['fichero']) && $_FILES['fichero']['error'] === UPLOAD_ERR_OK) {
    $tmp = $_FILES['fichero']['tmp_name'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $ext = strtolower(pathinfo($_FILES['fichero']['name'], PATHINFO_EXTENSION));

    if ($mime === 'application/pdf' && $ext === 'pdf') {
        $url = './archivos/' . $nroFactura . ".pdf";
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
header("Location: ./verCompras.php?ok");
exit;
