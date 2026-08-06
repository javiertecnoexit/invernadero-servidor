<?php
// ============================================================
// CONFIGURACIÓN DEL SERVIDOR — Invernadero IoT
// Lee variables de entorno (usadas en EasyPanel/Docker).
// Si no están definidas, usa los valores por defecto de abajo.
// ============================================================

function env($nombre, $defecto) {
    $v = getenv($nombre);
    return ($v === false || $v === '') ? $defecto : $v;
}

// --- Base de datos ---
// En EasyPanel, DB_HOST es el nombre interno del servicio MariaDB.
define('DB_HOST', env('DB_HOST', 'fitba_invernadero_cafe'));
define('DB_USER', env('DB_USER', 'invernadero_cafe'));
define('DB_PASS', env('DB_PASS', 'cafe2026'));
define('DB_NAME', env('DB_NAME', 'invernadero_cafe'));

// --- Clave de la API ---
// IMPORTANTE: DEBE coincidir con API_KEY en el firmware del ESP32
// (archivo: invernadero/src/config.h, línea 64)
define('API_KEY', env('API_KEY', '1c0ed2c4-4479-4952-ae74-336e586d759d'));

// --- Identificador del dispositivo esperado ---
define('DEVICE_ID_VALIDO', 'invernadero_01');

// --- Zona horaria para el dashboard (visualización) ---
// La BD guarda el timestamp del sensor en UTC; el dashboard convierte a esta zona.
date_default_timezone_set(env('TZ', 'America/Argentina/Buenos_Aires'));
