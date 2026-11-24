<?php
include('./Template/Cabecera.php');
include('./Modelo/Conexion.php');
include_once('./Modelo/Auth.php');
requerirAdmin($conexion);

$idUsuario = (int)($_GET['no'] ?? 0);
$stmt = $conexion->prepare("SELECT id_usuario, usuario, nombre, apellido, id_tipoUsuario, id_EstadoUsuario FROM usuarios WHERE id_usuario = ?");
$stmt->bind_param("i", $idUsuario);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$u) {
    header("Location: ./usuarios.php?error");
    exit;
}
?>

<div class="centrado">
    <div class="centrado-vlv">
        <a href="./usuarios.php"><button class="btn btn-success">&larr;&nbsp;Atrás</button></a>
    </div>
    <div class="centrado--titulo">
        <h1>MODIFICAR USUARIO</h1>
    </div>
    <div class="centrado--form">
        <form action="./modUsuario.php" method="POST" class="centrado--form--form">
            <div class="labelInput">
                <label for="id">Usuario N°:</label>
                <input type="text" name="id" id="id" class="readonly" value="<?php echo (int)$u['id_usuario']; ?>" readonly>
            </div>
            <div class="labelInput">
                <label for="usuario">Usuario:</label>
                <input type="text" name="usuario" id="usuario" value="<?php echo e($u['usuario']); ?>" required>
            </div>
            <div class="labelInput">
                <label for="nombre">Nombre:</label>
                <input type="text" name="nombre" id="nombre" value="<?php echo e(nombrePropio($u['nombre'])); ?>" required>
            </div>
            <div class="labelInput">
                <label for="apellido">Apellido:</label>
                <input type="text" name="apellido" id="apellido" value="<?php echo e(nombrePropio($u['apellido'])); ?>" required>
            </div>
            <div class="labelInput">
                <label for="pass">Contraseña nueva:</label>
                <input type="password" name="pass" id="pass" placeholder="Dejar vacío para no cambiarla" autocomplete="new-password">
            </div>
            <div class="labelInput">
                <label>Tipo:</label>
                <select name="tipo" required>
                    <?php
                    $res = mysqli_query($conexion, "SELECT id_tipoUsuario, tipo FROM tipo_usuario ORDER BY tipo");
                    while ($o = mysqli_fetch_assoc($res)) { ?>
                    <option value="<?php echo (int)$o['id_tipoUsuario']; ?>" <?php echo $o['id_tipoUsuario'] == $u['id_tipoUsuario'] ? 'selected' : ''; ?>><?php echo e($o['tipo']); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="labelInput">
                <label>Estado:</label>
                <select name="estado" required>
                    <?php
                    $res = mysqli_query($conexion, "SELECT id_EstadoUsuario, estadoUsuario FROM estadousuario ORDER BY estadoUsuario");
                    while ($o = mysqli_fetch_assoc($res)) { ?>
                    <option value="<?php echo (int)$o['id_EstadoUsuario']; ?>" <?php echo $o['id_EstadoUsuario'] == $u['id_EstadoUsuario'] ? 'selected' : ''; ?>><?php echo e($o['estadoUsuario']); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="labelInputbtn">
                <button type="submit" class="btn btn-success">Modificar</button>
            </div>
        </form>
    </div>
</div>

<?php include('./Template/Pie.php'); ?>
