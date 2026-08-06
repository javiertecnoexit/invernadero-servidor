<?php
// ============================================================
// datos.php — devuelve las lecturas en JSON para el dashboard
// Uso: datos.php?rango=24h|7d|30d
// ============================================================

require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

$rango = isset($_GET['rango']) ? $_GET['rango'] : '24h';
switch ($rango) {
    case '7d':  $intervalo = '7 DAY';  break;
    case '30d': $intervalo = '30 DAY'; break;
    case '24h':
    default:    $rango = '24h'; $intervalo = '24 HOUR'; break;
}

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Conexión a BD falló']);
    exit;
}

// Solo lecturas sin error. timestamp ya está en UTC en la BD.
$sql = "SELECT timestamp, sensor, valor
        FROM lecturas
        WHERE device_id = :device
          AND es_error = 0
          AND timestamp >= (UTC_TIMESTAMP() - INTERVAL $intervalo)
        ORDER BY timestamp ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute(['device' => DEVICE_ID_VALIDO]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar por sensor y convertir la hora UTC a la zona configurada
$series = [];
foreach ($rows as $row) {
    $sensor = $row['sensor'];
    $local  = (new DateTime($row['timestamp'], new DateTimeZone('UTC')))
              ->setTimezone(new DateTimeZone(date_default_timezone_get()))
              ->format('Y-m-d H:i:s');
    if (!isset($series[$sensor])) $series[$sensor] = [];
    $series[$sensor][] = [$local, (float)$row['valor']];
}

// Devolver también el rango y las unidades conocidas
$unidades = [
    'Temp bajo' => '°C', 'Temp alto' => '°C', 'Temp suelo' => '°C', 'Temp Ext' => '°C',
    'Hum bajo' => '%', 'Hum alto' => '%', 'Hum suelo' => '%',
    'Presion Ext' => 'hPa',
];

echo json_encode([
    'rango'    => $rango,
    'unidades' => $unidades,
    'series'   => $series,
]);
