<?php
/**
 * GPSP — Configuración central
 * Copia este archivo como config.php y completa los valores reales.
 */

// ── Base de datos ─────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'gps');
define('DB_USER', 'CAMBIAR_USUARIO');
define('DB_PASS', 'CAMBIAR_PASSWORD');

// ── API Keys ──────────────────────────────────────────────
define('ANTHROPIC_API_KEY', 'sk-ant-CAMBIAR_API_KEY');

// ── Seguridad ─────────────────────────────────────────────
define('MAX_FAILED',   5);
define('LOCK_MINUTES', 15);
define('ALLOWED_ID_EMP', '');

// ── Mapa usuario → país ISO ───────────────────────────────
define('USER_PAIS', json_encode([
    'chilepreivms'  => 'CL',
    'perupreivms'   => 'PE',
    'brasilpreivms' => 'BR',
]));

function dbConnect(): PDO {
    try {
        $pdo = new PDO(
            "mysql:host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_NAME.";charset=utf8mb4",
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
        );
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Error de conexión a la base de datos']);
        exit;
    }
}

function setCorsHeaders(): void {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Auth-Token');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
}
