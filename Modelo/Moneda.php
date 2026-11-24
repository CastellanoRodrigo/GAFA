<?php
// Presentacion de montos segun su moneda (pesos = ARS, dolares = USD) y totales discriminados por moneda.

function tablaExiste($conexion, string $tabla): bool
{
    $stmt = $conexion->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->bind_param("s", $tabla);
    $stmt->execute();
    $existe = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    return $existe;
}

function columnaExiste($conexion, string $tabla, string $columna): bool
{
    $stmt = $conexion->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->bind_param("ss", $tabla, $columna);
    $stmt->execute();
    $existe = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    return $existe;
}

function simboloMoneda(?string $codigo): string
{
    $codigo = strtoupper(trim((string)$codigo));
    if ($codigo === 'USD') return 'US$';
    if ($codigo === 'ARS' || $codigo === '') return '$';
    return $codigo;
}

// "$ 1.500,00" para pesos y "US$ 1.500,00" para dolares
function montoMoneda($valor, ?string $codigo): string
{
    return simboloMoneda($codigo) . ' ' . number_format((float)$valor, 2, ',', '.');
}

function nombreMoneda(?string $codigo): string
{
    $c = strtoupper(trim((string)$codigo));
    return ['ARS' => 'Pesos', 'USD' => 'Dólares'][$c] ?? ($c !== '' ? $c : 'Sin moneda');
}

// Etiqueta visible: ARS en verde, USD en azul
function chipMoneda(?string $codigo): string
{
    $c = strtoupper(trim((string)$codigo));
    $clase = $c === 'USD' ? 'moneda-usd' : 'moneda-ars';
    return '<span class="chip-moneda ' . $clase . '">' . htmlspecialchars($c !== '' ? $c : '-', ENT_QUOTES, 'UTF-8') . '</span>';
}

// Suma un campo agrupando por moneda. Devuelve ['ARS' => ['cantidad'=>n, 'total'=>x], 'USD' => [...]],
// con pesos primero y dolares despues. Nunca mezcla monedas.
function totalesPorMoneda(array $filas, string $campoMonto, string $campoMoneda = 'moneda'): array
{
    $t = [];
    foreach ($filas as $f) {
        $m = strtoupper(trim((string)($f[$campoMoneda] ?? ''))) ?: '-';
        if (!isset($t[$m])) $t[$m] = ['cantidad' => 0, 'total' => 0.0];
        $t[$m]['cantidad']++;
        $t[$m]['total'] += (float)($f[$campoMonto] ?? 0);
    }
    uksort($t, function ($a, $b) {
        $orden = ['ARS' => 0, 'USD' => 1];
        return ($orden[$a] ?? 2) <=> ($orden[$b] ?? 2) ?: strcmp($a, $b);
    });
    return $t;
}
