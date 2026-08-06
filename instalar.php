<?php
// ============================================================
// INSTALADOR — crea la tabla "lecturas" en la base de datos.
// Abre este archivo UNA sola vez en el navegador y luego BÓRRALO
// del servidor. (También puedes usar esquema.sql en phpMyAdmin.)
// ============================================================

require_once 'config.php';

// Seguridad: solo permitir si la tabla NO existe aún.
$sql = "CREATE TABLE IF NOT EXISTS lecturas (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id   VARCHAR(32)  NOT NULL,
    timestamp   DATETIME     NOT NULL,
    sensor      VARCHAR(32)  NOT NULL,
    valor       FLOAT        NULL,
    unidad      VARCHAR(8)   NOT NULL DEFAULT '',
    es_error    TINYINT(1)   NOT NULL DEFAULT 0,
    recibido_en DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_lectura (device_id, timestamp, sensor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec($sql);
    echo "<h2 style='font-family:sans-serif'>✅ Tabla 'lecturas' creada correctamente.</h2>";
    echo "<p style='font-family:sans-serif'>Ahora <b>borra el archivo instalar.php del servidor</b> por seguridad.</p>";
} catch (PDOException $e) {
    echo "<h2 style='font-family:sans-serif'>❌ Error al crear la tabla</h2>";
    echo "<pre style='font-family:sans-serif'>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<p style='font-family:sans-serif'>Revisa las credenciales en <b>config.php</b> (DB_HOST, DB_USER, DB_PASS, DB_NAME).</p>";
}
