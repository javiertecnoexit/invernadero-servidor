<?php
// ============================================================
// datos.php — devuelve lecturas + estadísticas + métricas derivadas
// Uso: datos.php?rango=12h|24h|7d|30d
//
// Métricas derivadas (especificacion_dashboard_invernadero_cafe.md):
//   - VPD (Temp alto + Hum alto)
//   - Punto de rocío y margen de condensación (Temp bajo + Hum bajo)
//   - Amortiguación térmica (Temp bajo - Temp Ext)
//   - Gradiente vertical (Temp alto - Temp bajo)
//   - Tasa de cambio suavizada (media móvil) — Temp Ext y Temp interior
//   - Horas por zona de riesgo térmico (ventana nocturna 18:00-08:00)
//   - Agregación diaria (min/max/avg) para vistas 7d/30d
//   - Correlaciones (Pearson) entre relaciones de interés
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

function utcALocal($utc, $tz) {
    return (new DateTime($utc, new DateTimeZone('UTC')))
           ->setTimezone(new DateTimeZone($tz))
           ->format('Y-m-d H:i:s');
}

// series por sensor (tiempo local) + pivot por timestamp (UTC) para derivadas
$series = [];
$pivot  = [];
foreach ($rows as $row) {
    $sensor = $row['sensor'];
    $local  = utcALocal($row['timestamp'], $timezone);
    $valor  = (float)$row['valor'];
    if (!isset($series[$sensor])) $series[$sensor] = [];
    $series[$sensor][] = [$local, $valor];
    $pivot[$row['timestamp']][$sensor] = $valor;
}

// ---------- 2. Métricas derivadas (calculadas en backend, por timestamp) ----------
$derivadas = [];

// VPD: déficit de presión de vapor (kPa), sobre TEMP ALTO / HUM ALTO
function calcVPD($t, $h) {
    $es = 0.6108 * exp((17.27 * $t) / ($t + 237.3)); // presión de saturación (kPa)
    return $es * (1 - $h / 100);
}
// Punto de rocío (aprox. lineal) — TEMP BAJO / HUM BAJO
function calcPuntoRocio($t, $h) {
    return $t - ((100 - $h) / 5);
}

foreach ($pivot as $utc => $sens) {
    $ts = utcALocal($utc, $timezone);

    // VPD
    if (isset($sens['Temp alto'], $sens['Hum alto'])) {
        $derivadas['VPD'][] = [$ts, round(calcVPD($sens['Temp alto'], $sens['Hum alto']), 3)];
    }

    // Punto de rocío y margen de condensación
    if (isset($sens['Temp bajo'], $sens['Hum bajo'])) {
        $rocio = calcPuntoRocio($sens['Temp bajo'], $sens['Hum bajo']);
        $derivadas['Punto rocio'][]   = [$ts, round($rocio, 1)];
        $derivadas['Margen cond.'][]  = [$ts, round($sens['Temp bajo'] - $rocio, 1)];
    }

    // Amortiguación térmica: Temp bajo − Temp Ext (°C de protección real)
    if (isset($sens['Temp bajo'], $sens['Temp Ext'])) {
        $derivadas['Amortig. termica'][] = [$ts, round($sens['Temp bajo'] - $sens['Temp Ext'], 1)];
    }

    // Gradiente vertical: Temp alto − Temp bajo
    if (isset($sens['Temp alto'], $sens['Temp bajo'])) {
        $derivadas['Gradiente vert.'][] = [$ts, round($sens['Temp alto'] - $sens['Temp bajo'], 1)];
    }

    // Temperatura interior promedio (para tasa de cambio y correlaciones)
    if (isset($sens['Temp alto'], $sens['Temp bajo'])) {
        $derivadas['T interior'][] = [$ts, round(($sens['Temp alto'] + $sens['Temp bajo']) / 2, 1)];
    }
}

// Fusionar derivadas en `series` (los gráficos las consumen igual que las crudas)
foreach ($derivadas as $nombre => $pts) {
    $series[$nombre] = $pts;
}

// ---------- 3. Estadísticas por sensor ----------
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

// ---------- 4. Tasa de cambio suavizada (media móvil) ----------
function calcTasaSerie($puntos) {
    $out = [];
    $n = count($puntos);
    for ($i = 1; $i < $n; $i++) {
        $dtH = (strtotime($puntos[$i][0]) - strtotime($puntos[$i-1][0])) / 3600;
        if ($dtH <= 0 || $dtH > 6) continue;
        $dy = $puntos[$i][1] - $puntos[$i-1][1];
        $out[] = [$puntos[$i][0], round($dy / $dtH, 3)];
    }
    return $out;
}

function mediaMovil($serie, $ventana = 4) {
    $out = [];
    $n = count($serie);
    for ($i = 0; $i < $n; $i++) {
        $sum = 0; $c = 0;
        for ($j = max(0, $i - $ventana + 1); $j <= $i; $j++) { $sum += $serie[$j][1]; $c++; }
        $out[] = [$serie[$i][0], round($sum / $c, 3)];
    }
    return $out;
}

$tasa_series = [];
if (isset($series['Temp Ext'])) {
    $tasa_series['Temp Ext'] = mediaMovil(calcTasaSerie($series['Temp Ext']));
}
if (isset($derivadas['T interior'])) {
    $tasa_series['T interior'] = mediaMovil(calcTasaSerie($derivadas['T interior']));
}

// Tasa promedio global por sensor (resumen, °C/h) — se mantiene por compatibilidad
$tasas = [];
foreach ($series as $sensor => $puntos) {
    $raw = calcTasaSerie($puntos);
    if (count($raw) < 1) continue;
    $sum = 0;
    foreach ($raw as $p) $sum += $p[1];
    $tasas[$sensor] = round($sum / count($raw), 2);
}

// ---------- 5. Comparación 24h vs 24h anteriores (solo si rango >= 24h) ----------
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
        $local  = utcALocal($row['timestamp'], $timezone);
        if (!isset($comparacion[$sensor])) $comparacion[$sensor] = [];
        $comparacion[$sensor][] = [$local, (float)$row['valor']];
    }
}

// ---------- 6. Horas por zona de riesgo térmico (ventana nocturna 18:00-08:00) ----------
$zonas_riesgo = null;
$clasificarZona = function ($t) {
    if ($t <= 0)  return 'critico';
    if ($t < 5)   return 'riesgo_severo';
    if ($t < 10)  return 'estres';
    if ($t < 15)  return 'atencion';
    return 'normal';
};

if (isset($series['Temp bajo']) && count($series['Temp bajo']) > 1) {
    $pts = $series['Temp bajo'];
    $acum = ['normal' => 0, 'atencion' => 0, 'estres' => 0, 'riesgo_severo' => 0, 'critico' => 0];
    $enNoche = function ($h) { return $h >= 18 || $h < 8; };
    $n = count($pts);
    for ($i = 1; $i < $n; $i++) {
        $t1 = strtotime($pts[$i-1][0]);
        $t2 = strtotime($pts[$i][0]);
        $h1 = (int)date('G', $t1);
        $h2 = (int)date('G', $t2);
        if ($enNoche($h1) && $enNoche($h2)) {
            $dtMin = ($t2 - $t1) / 60;
            if ($dtMin > 0 && $dtMin <= 60) {
                $acum[$clasificarZona($pts[$i][1])] += $dtMin;
            }
        }
    }
    $zonas_riesgo = [
        'minutos'  => $acum,
        'horas'    => array_map(function ($m) { return round($m / 60, 1); }, $acum),
        'ventana'  => '18:00–08:00',
    ];
}

// ---------- 7. Agregación diaria (min/max/avg) para vistas 7d/30d ----------
$diario = [];
foreach ($series as $sensor => $puntos) {
    $agrupado = [];
    foreach ($puntos as $p) {
        $fecha = substr($p[0], 0, 10);
        if (!isset($agrupado[$fecha])) $agrupado[$fecha] = [];
        $agrupado[$fecha][] = $p[1];
    }
    ksort($agrupado);
    $fila = [];
    foreach ($agrupado as $fecha => $vals) {
        $fila[] = [
            'fecha'  => $fecha,
            'min'    => round(min($vals), 1),
            'max'    => round(max($vals), 1),
            'avg'    => round(array_sum($vals) / count($vals), 1),
            'puntos' => count($vals),
        ];
    }
    if (count($fila) > 0) $diario[$sensor] = $fila;
}

// ---------- 8. Correlaciones (Pearson) entre relaciones de interés ----------
function pearson($a, $b) {
    $n = count($a);
    if ($n < 3) return null;
    $mx = array_sum($a) / $n; $my = array_sum($b) / $n;
    $num = 0; $dx = 0; $dy = 0;
    for ($i = 0; $i < $n; $i++) {
        $nx = $a[$i] - $mx; $ny = $b[$i] - $my;
        $num += $nx * $ny; $dx += $nx * $nx; $dy += $ny * $ny;
    }
    if ($dx == 0 || $dy == 0) return null;
    return $num / sqrt($dx * $dy);
}

$correlaciones = [];
$relaciones = [
    'T ext vs T int'   => ['Temp Ext', 'T interior'],
    'H suelo vs H int' => ['Hum suelo', 'H interior'],
    'T bajo vs T suelo'=> ['Temp bajo', 'Temp suelo'],
];
// 'H interior' promedio (alto+bajo) por timestamp
if (!isset($derivadas['H interior'])) {
    $hInt = [];
    foreach ($pivot as $utc => $sens) {
        if (isset($sens['Hum alto'], $sens['Hum bajo'])) {
            $hInt[] = [utcALocal($utc, $timezone), ($sens['Hum alto'] + $sens['Hum bajo']) / 2];
        }
    }
    $derivadas['H interior'] = $hInt;
}
if (isset($derivadas['H interior']) && !isset($series['H interior'])) {
    $series['H interior'] = $derivadas['H interior'];
}

foreach ($relaciones as $nombre => $par) {
    $x = isset($series[$par[0]]) ? $series[$par[0]] : [];
    $y = isset($derivadas[$par[1]]) ? $derivadas[$par[1]] : [];
    if (count($x) < 3 || count($y) < 3) continue;
    $mapX = []; foreach ($x as $p) $mapX[$p[0]] = $p[1];
    $mapY = []; foreach ($y as $p) $mapY[$p[0]] = $p[1];
    $ax = []; $ay = [];
    foreach ($mapX as $ts => $vx) {
        if (isset($mapY[$ts])) { $ax[] = $vx; $ay[] = $mapY[$ts]; }
    }
    $r = pearson($ax, $ay);
    if ($r !== null) {
        $correlaciones[$nombre] = ['r' => round($r, 3), 'n' => count($ax)];
    }
}

// ---------- 9. Unidades ----------
$unidades = [
    'Temp bajo' => '°C', 'Temp alto' => '°C', 'Temp suelo' => '°C', 'Temp Ext' => '°C',
    'Hum bajo' => '%', 'Hum alto' => '%', 'Hum suelo' => '%',
    'Presion Ext' => 'hPa',
    'VPD' => 'kPa',
    'Punto rocio' => '°C',
    'Margen cond.' => '°C',
    'Amortig. termica' => '°C',
    'Gradiente vert.' => '°C',
    'T interior' => '°C',
];

echo json_encode([
    'rango'         => $rango,
    'unidades'      => $unidades,
    'series'        => $series,
    'stats'         => $stats,
    'tasas'         => $tasas,
    'tasa_series'   => $tasa_series,
    'comparacion'   => $comparacion,
    'zonas_riesgo'  => $zonas_riesgo,
    'diario'        => $diario,
    'correlaciones' => $correlaciones,
]);