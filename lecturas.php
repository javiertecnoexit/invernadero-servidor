<?php
// ============================================================
// API — recibe las lecturas del ESP32 (POST /lecturas.php)
// Contrato completo en: especificacion_unificada_invernadero.md (sección 9)
// ============================================================

require_once 'config.php';

// ---------- Utilidades de respuesta ----------
function responder($code, $status, $mensaje = '') {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    if ($status === 'ok') {
        echo json_encode(['status' => 'ok']);
    } else {
        echo json_encode(['status' => 'error', 'mensaje' => $mensaje]);
    }
    exit;
}

// ---------- 1. Autenticación (X-API-Key) ----------
$key = isset($_SERVER['HTTP_X_API_KEY']) ? trim($_SERVER['HTTP_X_API_KEY']) : '';
if ($key === '' || !hash_equals(API_KEY, $key)) {
    responder(401, 'error', 'Clave de API inválida o ausente');
}

// ---------- 2. Solo método POST ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(405, 'error', 'Método no permitido. Usar POST');
}

// ---------- 3. Leer y validar el JSON ----------
$raw = file_get_contents('php://input');
if ($raw === false || trim($raw) === '') {
    responder(400, 'error', 'Cuerpo vacío');
}
$data = json_decode($raw, true);
if ($data === null) {
    responder(400, 'error', 'JSON mal formado');
}

// ---------- 4. Validar campos obligatorios ----------
if (empty($data['device_id']) || !isset($data['timestamp']) || empty($data['lecturas']) || !is_array($data['lecturas'])) {
    responder(400, 'error', 'Campos obligatorios: device_id, timestamp, lecturas (array)');
}

$deviceId  = trim($data['device_id']);
$timestamp = trim($data['timestamp']);

// Validar timestamp ISO 8601 (se acepta "YYYY-MM-DDTHH:MM:SSZ" o sin Z)
$formato = strlen($timestamp) > 19 ? 'Y-m-d\TH:i:s\Z' : 'Y-m-d\TH:i:s';
$dt = DateTime::createFromFormat($formato, $timestamp);
if (!$dt || $dt->format($formato) !== $timestamp) {
    responder(400, 'error', 'Timestamp inválido. Formato ISO 8601 esperado (ej. 2026-06-30T14:35:00Z)');
}
$timestampDb = $dt->format('Y-m-d H:i:s');

// ---------- 5. Conexión a la base de datos ----------
try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log('BD conexión: ' . $e->getMessage());
    responder(500, 'error', 'Error de conexión a la base de datos');
}

// ---------- 6. Insertar las lecturas (una fila por sensor) ----------
// ON DUPLICATE KEY UPDATE id=id -> si el dato ya existe (reintento del ESP32),
// se ignora (no duplica ni pisa). La unicidad la da (device_id, timestamp, sensor).
$sql = "INSERT INTO lecturas (device_id, timestamp, sensor, valor, unidad, es_error)
        VALUES (:device_id, :timestamp, :sensor, :valor, :unidad, :es_error)
        ON DUPLICATE KEY UPDATE id = id";
$stmt = $pdo->prepare($sql);

$pdo->beginTransaction();
try {
    foreach ($data['lecturas'] as $lectura) {
        if (!is_array($lectura)) continue;

        $nombre = isset($lectura['nombre']) ? trim($lectura['nombre']) : '';
        $unidad = isset($lectura['unidad']) ? trim($lectura['unidad']) : '';
        $error  = !empty($lectura['error']);

        // Si el sensor está en error, el valor no es confiable -> NULL
        $valor = $error ? null : (isset($lectura['valor']) && is_numeric($lectura['valor']) ? (float)$lectura['valor'] : null);

        if ($nombre === '') continue; // ignorar entradas sin nombre

        $stmt->execute([
            'device_id' => $deviceId,
            'timestamp' => $timestampDb,
            'sensor'    => $nombre,
            'valor'     => $valor,
            'unidad'    => $unidad,
            'es_error'  => $error ? 1 : 0,
        ]);
    }
    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('BD insert: ' . $e->getMessage());
    responder(500, 'error', 'Error al guardar las lecturas');
}

// ---------- 7. Éxito ----------
// El ESP32 solo considera "enviado" un HTTP 200 con {"status":"ok"}
responder(200, 'ok');
