<?php
/**
 * GPSP — Análisis de foto con Claude Vision
 * Archivo: analizar_foto.php
 * Versión: V4.0 | 2026-06-23
 *
 * Recibe una imagen (multipart/form-data) y el tipo de equipo,
 * la envía a Claude API y devuelve los datos extraídos.
 *
 * POST: imagen (file) + tipo (galileosky|mdvr|iridium|tablet|sensor|vehiculo)
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Auth-Token');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'Método no permitido']); exit; }

// ── Validar archivo ───────────────────────────────────────
if (empty($_FILES['imagen']['tmp_name'])) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>'No se recibió imagen']);
    exit;
}

$tipo = trim($_POST['tipo'] ?? 'general');
$file = $_FILES['imagen'];
$ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = ['jpg','jpeg','png','webp','heic','heif'];

if (!in_array($ext, $allowed)) {
    echo json_encode(['ok'=>false,'error'=>'Formato de imagen no soportado']);
    exit;
}
if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(['ok'=>false,'error'=>'Imagen demasiado grande (máx 10MB)']);
    exit;
}

// ── Convertir imagen a base64 ─────────────────────────────
$imageData    = base64_encode(file_get_contents($file['tmp_name']));
$mediaTypeMap = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png',
                 'webp'=>'image/webp','heic'=>'image/heic','heif'=>'image/heif'];
$mediaType    = $mediaTypeMap[$ext] ?? 'image/jpeg';

// ── Prompt según tipo de equipo ───────────────────────────
$prompts = [
    'galileosky' => "Analiza esta etiqueta de un equipo Galileosky (rastreador GPS). 
        Extrae EXACTAMENTE los siguientes datos si están presentes:
        - IMEI (número de 15 dígitos, puede estar precedido por 'IMEI:')
        - SN o Serial Number
        - Modelo
        Responde SOLO en JSON válido sin texto adicional, sin bloques de código markdown:
        {\"imei\": \"...\", \"serial\": \"...\", \"modelo\": \"...\"}
        Si un campo no está visible, usa null.",

    'mdvr' => "Analiza esta etiqueta de un MDVR Howen (Hero-ME32-04 o similar).
        Extrae EXACTAMENTE:
        - IMEI (15 dígitos, precedido por 'IMEI:')
        - SN (Serial Number, precedido por 'SN:')
        - Modelo (precedido por 'Model:')
        Responde SOLO en JSON válido sin texto adicional ni bloques markdown:
        {\"imei\": \"...\", \"serial\": \"...\", \"modelo\": \"...\"}
        Si un campo no está visible, usa null.",

    'iridium' => "Analiza esta etiqueta del módem Iridium Satellite.
        Extrae EXACTAMENTE:
        - IMEI (número de 15 dígitos, precedido por 'IMEI:') — los Iridium empiezan con 300
        - Serial Number (precedido por 'SERIAL #')
        - Modelo (ej: SBDG9603A)
        Responde SOLO en JSON válido sin texto adicional ni bloques markdown:
        {\"imei\": \"...\", \"serial\": \"...\", \"modelo\": \"...\"}
        Si un campo no está visible, usa null.",

    'tablet' => "Analiza esta imagen. Puede ser una tarjeta SIM o una etiqueta de tablet MDT.
        Si es SIM card: extrae el ICCID (número de 19-20 dígitos que aparece impreso o en código de barras, usualmente empieza con 89).
        Si es tablet: extrae IMEI, Serial Number y Modelo.
        Responde SOLO en JSON válido sin texto adicional ni bloques markdown:
        {\"iccid\": \"...\", \"imei\": \"...\", \"serial\": \"...\", \"modelo\": \"...\"}
        Si un campo no aplica o no está visible, usa null.",

    'sensor' => "Analiza esta etiqueta del sensor DU-BLE (3Scort o similar).
        Extrae EXACTAMENTE:
        - MAC Bluetooth (formato XX:XX:XX:XX:XX:XX o similar, puede estar precedido por 'E0:', etc.)
        - Serial Number o número de dispositivo
        Responde SOLO en JSON válido sin texto adicional ni bloques markdown:
        {\"mac\": \"...\", \"serial\": \"...\"}
        Si un campo no está visible, usa null.",

    'vehiculo' => "Analiza esta imagen de un vehículo o su placa de identificación.
        Extrae si es visible:
        - Número de placa/matrícula
        - Marca del vehículo
        - Modelo (si es visible)
        Responde SOLO en JSON válido sin texto adicional ni bloques markdown:
        {\"placa\": \"...\", \"marca\": \"...\", \"modelo\": \"...\"}
        Si un campo no está visible, usa null.",

    'general' => "Analiza esta etiqueta de equipo técnico y extrae todos los números importantes que veas: IMEI, serial, MAC, ICCID, o cualquier código de identificación.
        Responde SOLO en JSON válido: {\"datos\": [{\"tipo\": \"IMEI\", \"valor\": \"...\"}]}",
];

$prompt = $prompts[$tipo] ?? $prompts['general'];

// ── Llamar a Claude API ───────────────────────────────────
$payload = [
    'model'      => 'claude-sonnet-4-6',
    'max_tokens' => 300,
    'messages'   => [[
        'role'    => 'user',
        'content' => [
            [
                'type'   => 'image',
                'source' => [
                    'type'       => 'base64',
                    'media_type' => $mediaType,
                    'data'       => $imageData,
                ],
            ],
            [
                'type' => 'text',
                'text' => $prompt,
            ],
        ],
    ]],
];

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . ANTHROPIC_API_KEY,
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_TIMEOUT        => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$response || $httpCode !== 200) {
    error_log("Claude API error: HTTP {$httpCode} — {$response}");
    echo json_encode(['ok'=>false,'error'=>'Error al analizar la imagen. Ingrese los datos manualmente.']);
    exit;
}

$apiData = json_decode($response, true);
$text    = $apiData['content'][0]['text'] ?? '';

// Limpiar posibles bloques markdown que Claude añada
$text = preg_replace('/```json\s*|\s*```/', '', trim($text));

// Parsear JSON de respuesta
$extracted = json_decode($text, true);
if (!$extracted) {
    echo json_encode(['ok'=>false,'error'=>'No se pudieron extraer datos. Verifique la imagen.','raw'=>$text]);
    exit;
}

echo json_encode([
    'ok'   => true,
    'tipo' => $tipo,
    'data' => $extracted,
]);
