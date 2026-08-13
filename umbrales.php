<?php
// ============================================================
// umbrales.php — gestión de umbrales configurables del dashboard
//   GET  /umbrales.php          -> lista de umbrales (con defaults si tabla vacía)
//   POST /umbrales.php          -> actualiza umbrales (requiere X-API-Key)
//                                  body: { "umbrales": [ {nombre, valor, activo} ] }
// La tabla se crea automáticamente en el primer acceso.
// ============================================================

require_once 'config.php';

function responder($code, $data) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function conectarBD() {
    try {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        error_log('umbrales BD conexión: ' . $e->getMessage());
        responder(500, ['error' => 'Error de conexión a la base de datos']);
    }
}

function crearTabla($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS umbrales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(50) NOT NULL UNIQUE,
        valor FLOAT NOT NULL,
        unidad VARCHAR(10) NOT NULL DEFAULT '',
        activo TINYINT(1) NOT NULL DEFAULT 1,
        descripcion VARCHAR(200) NOT NULL DEFAULT '',
        actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sql);
}

// Umbrales iniciales (punto de partida, editables en el dashboard)
$DEFAULTS = [
    ['nombre' => 'buffer_termico_min',  'valor' => 3.0, 'unidad' => '°C', 'activo' => 1, 'descripcion' => 'Amortiguación térmica mínima (Temp bajo - Temp Ext)'],
    ['nombre' => 'temp_bajo_estres',    'valor' => 10.0, 'unidad' => '°C', 'activo' => 1, 'descripcion' => 'Estrés fisiológico por frío (Temp bajo)'],
    ['nombre' => 'temp_bajo_riesgo',    'valor' => 5.0, 'unidad' => '°C', 'activo' => 1, 'descripcion' => 'Zona de riesgo severo (Temp bajo)'],
    ['nombre' => 'temp_bajo_critico',   'valor' => 0.0, 'unidad' => '°C', 'activo' => 1, 'descripcion' => 'Zona crítica / helada (Temp bajo)'],
    ['nombre' => 'vpd_min',             'valor' => 0.4, 'unidad' => 'kPa', 'activo' => 1, 'descripcion' => 'VPD mínimo (riesgo fúngico)'],
    ['nombre' => 'vpd_max',             'valor' => 0.8, 'unidad' => 'kPa', 'activo' => 1, 'descripcion' => 'VPD máximo (estrés hídrico)'],
    ['nombre' => 'margen_rocio_min',    'valor' => 2.0, 'unidad' => '°C', 'activo' => 1, 'descripcion' => 'Margen de condensación mínimo (Temp bajo - Punto rocío)'],
];

function sembrarDefaults($pdo) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO umbrales (nombre, valor, unidad, activo, descripcion) VALUES (:n, :v, :u, :a, :d)");
    foreach ($GLOBALS['DEFAULTS'] as $u) {
        $stmt->execute([':n' => $u['nombre'], ':v' => $u['valor'], ':u' => $u['unidad'], ':a' => $u['activo'], ':d' => $u['descripcion']]);
    }
}

function listarUmbrales($pdo) {
    $stmt = $pdo->query("SELECT nombre, valor, unidad, activo, descripcion FROM umbrales ORDER BY id");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$pdo = conectarBD();
crearTabla($pdo);
sembrarDefaults($pdo);

$metodo = $_SERVER['REQUEST_METHOD'];

// ---------- GET: listar ----------
if ($metodo === 'GET') {
    $items = listarUmbrales($pdo);
    // asegurar que todos los defaults existan en la respuesta
    $porNombre = [];
    foreach ($items as $i) $porNombre[$i['nombre']] = $i;
    $resultado = [];
    foreach ($DEFAULTS as $u) {
        if (isset($porNombre[$u['nombre']])) $resultado[] = $porNombre[$u['nombre']];
    }
    responder(200, ['umbrales' => $resultado]);
}

// ---------- POST: actualizar (requiere API key) ----------
if ($metodo === 'POST') {
    $key = isset($_SERVER['HTTP_X_API_KEY']) ? trim($_SERVER['HTTP_X_API_KEY']) : '';
    if ($key === '' || !hash_equals(API_KEY, $key)) {
        responder(401, ['error' => 'Clave de API inválida o ausente']);
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!isset($data['umbrales']) || !is_array($data['umbrales']) || count($data['umbrales']) === 0) {
        responder(400, ['error' => 'Se esperaba { "umbrales": [...] }']);
    }

    $stmt = $pdo->prepare("UPDATE umbrales SET valor = :v, activo = :a WHERE nombre = :n");
    $nombresValidos = array_column($DEFAULTS, 'nombre');
    $actualizados = 0;
    foreach ($data['umbrales'] as $u) {
        if (!isset($u['nombre']) || !in_array($u['nombre'], $nombresValidos, true)) continue;
        $valor = isset($u['valor']) && is_numeric($u['valor']) ? (float)$u['valor'] : null;
        $activo = isset($u['activo']) ? ($u['activo'] ? 1 : 0) : 1;
        if ($valor === null) continue;
        $stmt->execute([':v' => $valor, ':a' => $activo, ':n' => $u['nombre']]);
        $actualizados++;
    }

    if ($actualizados === 0) {
        responder(400, ['error' => 'No se actualizó ningún umbral válido']);
    }

    responder(200, ['status' => 'ok', 'actualizados' => $actualizados]);
}

responder(405, ['error' => 'Método no permitido']);