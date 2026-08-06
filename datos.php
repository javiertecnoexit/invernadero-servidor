<?php
// ============================================================
// datos.php — devuelve lecturas + estadísticas + métricas
// Uso: datos.php?rango=12h|24h|7d|30d
// ============================================================

require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

$rango = isset($_GET['rango']) ? $_GET['rango'] : '24h';
switch ($rango) {
    case '12h': $intervalo = '12 HOUR'; break;
    case '7d':  $intervalo = '7 DAY';  break;
    case '30d': $intervalo = '30 DAY'; break;
    case '24h':
    default:    $rango = '24h'; $intervalo = '24 HOUR'; break;
}

$timezone = date_default_timezone_get();

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Conexión a BD falló']);
    exit;
}

// ---------- 1. Lecturas del período ----------
$sql = "SELECT timestamp, sensor, valor
        FROM lecturas
        WHERE device_id = :device
          AND es_error = 0
          AND valor IS NOT NULL
          AND timestamp >= (UTC_TIMESTAMP() - INTERVAL $intervalo)
        ORDER BY timestamp ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute(['device' => DEVICE_ID_VALIDO]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$series = [];
foreach ($rows as $row) {
    $sensor = $row['sensor'];
    $local  = (new DateTime($row['timestamp'], new DateTimeZone('UTC')))
              ->setTimezone(new DateTimeZone($timezone))
              ->format('Y-m-d H:i:s');
    if (!isset($series[$sensor])) $series[$sensor] = [];
    $series[$sensor][] = [$local, (float)$row['valor']];
}

// ---------- 2. Estadísticas por sensor ----------
$stats = [];
foreach ($series as $sensor => $puntos) {
    $valores = array_column($puntos, 1);
    $stats[$sensor] = [
        'min'  => round(min($valores), 1),
        'max'  => round(max($valores), 1),
        'avg'  => round(array_sum($valores) / count($valores), 1),
        'puntos' => count($valores),
    ];
}

// ---------- 3. Tasa de cambio (dT/dt) por hora ----------
// Calcula la pendiente promedio por hora para cada sensor
$tasas = [];
foreach ($series as $sensor => $puntos) {
    if (count($puntos) < 2) continue;
    $sumPendientes = 0;
    $contador = 0;
    for ($i = 1; $i < count($puntos); $i++) {
        $t1 = strtotime($puntos[$i-1][0]);
        $t2 = strtotime($puntos[$i][0]);
        $dt_horas = ($t2 - $t1) / 3600;
        if ($dt_horas <= 0) continue;
        $dy = $puntos[$i][1] - $puntos[$i-1][1];
        $sumPendientes += $dy / $dt_horas;
        $contador++;
    }
    if ($contador > 0) {
        $tasas[$sensor] = round($sumPendientes / $contador, 2);
    }
}

// ---------- 4. Comparación 24h vs 24h anteriores (solo si rango >= 24h) ----------
$comparacion = null;
if (in_array($rango, ['24h', '7d', '30d'])) {
    $sqlComp = "SELECT timestamp, sensor, valor
                FROM lecturas
                WHERE device_id = :device
                  AND es_error = 0
                  AND valor IS NOT NULL
                  AND timestamp >= (UTC_TIMESTAMP() - INTERVAL 48 HOUR)
                  AND timestamp <  (UTC_TIMESTAMP() - INTERVAL 24 HOUR)
                ORDER BY timestamp ASC";
    $stmtComp = $pdo->prepare($sqlComp);
    $stmtComp->execute(['device' => DEVICE_ID_VALIDO]);
    $rowsComp = $stmtComp->fetchAll(PDO::FETCH_ASSOC);

    $comparacion = [];
    foreach ($rowsComp as $row) {
        $sensor = $row['sensor'];
        $local  = (new DateTime($row['timestamp'], new DateTimeZone('UTC')))
                  ->setTimezone(new DateTimeZone($timezone))
                  ->format('Y-m-d H:i:s');
        if (!isset($comparacion[$sensor])) $comparacion[$sensor] = [];
        $comparacion[$sensor][] = [$local, (float)$row['valor']];
    }
}

// ---------- 5. Unidades ----------
$unidades = [
    'Temp bajo' => '°C', 'Temp alto' => '°C', 'Temp suelo' => '°C', 'Temp Ext' => '°C',
    'Hum bajo' => '%', 'Hum alto' => '%', 'Hum suelo' => '%',
    'Presion Ext' => 'hPa',
];

echo json_encode([
    'rango'       => $rango,
    'unidades'    => $unidades,
    'series'      => $series,
    'stats'       => $stats,
    'tasas'       => $tasas,
    'comparacion' => $comparacion,
]);
