<?php
// Logica compartida del alquiler de parcelas: contratos, estados y cuotas de cobro.
require_once __DIR__ . '/Moneda.php';

// Meses entre cobros. 0 = pago unico al inicio del contrato.
const PERIODICIDADES = ['Mensual' => 1, 'Bimestral' => 2, 'Trimestral' => 3, 'Semestral' => 6, 'Anual' => 12, 'Pago único' => 0];
const DIAS_POR_VENCER = 60;
const ESTADOS_CONTRATO = ['Vigente', 'Por vencer', 'Vencido', 'Futuro'];

function tablasAlquilerListas($conexion): bool
{
    return tablaExiste($conexion, 'detallealquiler') && tablaExiste($conexion, 'cobrosalquiler');
}

function fechaValidaAlq($f): bool
{
    $d = DateTime::createFromFormat('Y-m-d', (string)$f);
    return $d && $d->format('Y-m-d') === $f;
}

// Suma meses sin "desbordar" (31/01 + 1 mes = 28/02, no 03/03) y sin arrastrar error de un mes al siguiente.
function sumarMeses(DateTime $desde, int $meses): DateTime
{
    $dia = (int)$desde->format('j');
    $n = (clone $desde)->modify('first day of this month')->modify("+$meses months");
    $n->setDate((int)$n->format('Y'), (int)$n->format('n'), min($dia, (int)$n->format('t')));
    return $n;
}

// Fechas de vencimiento de las cuotas: una al inicio de cada periodo, mientras comiencen antes del fin del contrato.
function generarVencimientos(string $inicio, string $fin, int $mesesPeriodo): array
{
    $ini = new DateTime($inicio);
    $limite = new DateTime($fin);
    if ($mesesPeriodo === 0) {
        return [$ini->format('Y-m-d')];
    }
    $fechas = [];
    for ($i = 0; $i < 600; $i++) {
        $d = sumarMeses($ini, $i * $mesesPeriodo);
        // La primera cuota siempre existe; las demas deben caer antes del ultimo dia del contrato
        if ($i > 0 && $d >= $limite) break;
        $fechas[] = $d->format('Y-m-d');
    }
    return $fechas;
}

function estadoContrato(string $inicio, string $fin, ?string $hoy = null): string
{
    $hoy = $hoy ?? date('Y-m-d');
    if ($hoy < $inicio) return 'Futuro';
    if ($hoy > $fin) return 'Vencido';
    $dias = (new DateTime($hoy))->diff(new DateTime($fin))->days;
    return $dias <= DIAS_POR_VENCER ? 'Por vencer' : 'Vigente';
}

function claseEstado(string $estado): string
{
    return ['Vigente' => 'est-ok', 'Por vencer' => 'est-aviso', 'Vencido' => 'est-mal', 'Futuro' => 'est-info',
            'Cobrado' => 'est-ok', 'Pendiente' => 'est-info', 'Vencida' => 'est-mal'][$estado] ?? 'est-info';
}

function filtrosAlquileres(array $src): array
{
    $estado = (string)($src['estado'] ?? '');
    return [
        'estado' => in_array($estado, ESTADOS_CONTRATO, true) ? $estado : '',
        'q'      => trim((string)($src['q'] ?? '')),
    ];
}

function hayFiltrosAlquileres(array $f): bool
{
    return $f['estado'] !== '' || $f['q'] !== '';
}

// Contratos con su proyecto, estado calculado y resumen de cobros.
function consultarAlquileres($conexion, array $f): array
{
    $sql = "SELECT da.id_DetalleAlquiler, da.id_Proyecto, da.inquilino, da.contacto, da.fechaInicio, da.fechaFin,
                   da.canon, da.periodicidad, da.id_Moneda, da.tipoCambio, da.observaciones,
                   p.nombreProyecto, p.id_Parcela, p.cantidadHas, m.moneda,
                   (SELECT COUNT(*) FROM cobrosalquiler c WHERE c.id_DetalleAlquiler = da.id_DetalleAlquiler AND c.fechaPago IS NULL) AS pendientes,
                   (SELECT COALESCE(SUM(c.monto), 0) FROM cobrosalquiler c WHERE c.id_DetalleAlquiler = da.id_DetalleAlquiler AND c.fechaPago IS NULL AND c.fechaVencimiento < CURDATE()) AS vencido,
                   (SELECT COALESCE(SUM(c.monto), 0) FROM cobrosalquiler c WHERE c.id_DetalleAlquiler = da.id_DetalleAlquiler AND c.fechaPago IS NOT NULL) AS cobrado,
                   (SELECT COALESCE(SUM(c.monto), 0) FROM cobrosalquiler c WHERE c.id_DetalleAlquiler = da.id_DetalleAlquiler AND c.fechaPago IS NULL) AS porCobrar,
                   (SELECT MIN(c.fechaVencimiento) FROM cobrosalquiler c WHERE c.id_DetalleAlquiler = da.id_DetalleAlquiler AND c.fechaPago IS NULL) AS proximo
            FROM detallealquiler da
            JOIN proyectos p ON p.id_Proyecto = da.id_Proyecto
            LEFT JOIN moneda m ON m.id_Moneda = da.id_Moneda
            WHERE 1 = 1";
    $tipos = '';
    $params = [];
    if ($f['q'] !== '') {
        $sql .= " AND (da.inquilino LIKE ? OR p.nombreProyecto LIKE ?)";
        $like = '%' . addcslashes($f['q'], '%_\\') . '%';
        $tipos .= 'ss';
        $params[] = $like;
        $params[] = $like;
    }
    $sql .= " ORDER BY da.fechaFin ASC, da.id_DetalleAlquiler ASC";

    $stmt = $conexion->prepare($sql);
    if ($params) {
        $stmt->bind_param($tipos, ...$params);
    }
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $salida = [];
    foreach ($filas as $fila) {
        $fila['estado'] = estadoContrato($fila['fechaInicio'], $fila['fechaFin']);
        if ($f['estado'] === '' || $fila['estado'] === $f['estado']) {
            $salida[] = $fila;
        }
    }
    return $salida;
}

function fechaCorta($f): string
{
    return $f ? date('d/m/Y', strtotime($f)) : '';
}

