<?php
/**
 * GPSP — API Guardar Pre-Registro con fotos + email
 * Archivo: save_preregistro.php
 * Versión: V5.7 | 2026-06-27
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS, GET');
header('Access-Control-Allow-Headers: Content-Type, X-Auth-Token');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ── DEBUG GET — ver configuración del servidor ────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['debug'])) {
    echo json_encode([
        'debug'          => true,
        'php_version'    => PHP_VERSION,
        'post_max_size'  => ini_get('post_max_size'),
        'upload_max'     => ini_get('upload_max_filesize'),
        'memory_limit'   => ini_get('memory_limit'),
        'server_time'    => date('Y-m-d H:i:s'),
        'method'         => $_SERVER['REQUEST_METHOD'],
    ], JSON_PRETTY_PRINT);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'Método no permitido']);
    exit;
}

// Intentar aumentar límites en runtime
@ini_set('upload_max_filesize', '30M');
@ini_set('post_max_size', '50M');
@ini_set('memory_limit', '256M');

// ── Helpers ───────────────────────────────────────────────
function s(?string $v, int $max=255): ?string {
    if ($v===null || trim($v)==='') return null;
    return mb_substr(trim(strip_tags($v)), 0, $max);
}
function sf(?string $v): ?float {
    if ($v===null || trim($v)==='') return null;
    $f = filter_var($v, FILTER_VALIDATE_FLOAT); return $f===false ? null : $f;
}
function si(?string $v): ?int {
    if ($v===null || trim($v)==='') return null;
    $i = filter_var($v, FILTER_VALIDATE_INT); return $i===false ? null : $i;
}

// FormData (multipart) — datos de texto en campo _json, fotos en $_FILES
// Se usa _json para evitar que post_max_size vacíe $_POST cuando hay blobs grandes
if (!empty($_POST['_json'])) {
    $data = json_decode($_POST['_json'], true) ?? [];
} else {
    // Fallback: leer $_POST directo (compatibilidad)
    $data = $_POST;
}

// ── DEBUG temporal — quitar después de resolver el bug ────
if (!empty($_GET['debug'])) {
    echo json_encode([
        'debug'          => true,
        'has_json'       => !empty($_POST['_json']),
        'post_keys'      => array_keys($_POST),
        'data_placa'     => $data['placa'] ?? 'AUSENTE',
        'data_marca'     => $data['marca'] ?? 'AUSENTE',
        'files'          => array_keys($_FILES),
        'post_max_size'  => ini_get('post_max_size'),
        'upload_max'     => ini_get('upload_max_filesize'),
        'content_length' => $_SERVER['CONTENT_LENGTH'] ?? '?',
        'json_raw_start' => substr($_POST['_json'] ?? '', 0, 100),
    ]);
    exit;
}

// Validación mínima
foreach (['placa','marca','modelo'] as $f) {
    if (empty(trim($data[$f] ?? ''))) {
        http_response_code(422);
        echo json_encode(['ok'=>false,'error'=>"Campo requerido: {$f}"]);
        exit;
    }
}

$pdo = dbConnect();

// Verificar placa duplicada
$chk = $pdo->prepare("SELECT id FROM preregistro_ivms WHERE placa = ? LIMIT 1");
$chk->execute([strtoupper(s($data['placa'], 20))]);
if ($chk->fetch()) {
    http_response_code(409);
    echo json_encode(['ok'=>false,'error'=>'Ya existe un pre-registro con esta placa']);
    exit;
}

$codigo = 'PREG-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

// ── Guardar fotos ─────────────────────────────────────────
// Carpeta: uploads/fotos_ivms/PREG-YYYYMMDD-XXXXXX/
$fotoPaths = [];
$fotoFields = ['foto_imei_galileosky','foto_chip_galileosky','foto_imei_mdvr','foto_chip_mdvr','foto_imei_iridium','foto_chip_tablet','foto_imei_tablet','foto_mac_sensor_angulo','foto_vehiculo'];
$uploadDir  = __DIR__ . '/uploads/fotos_ivms/' . $codigo . '/';

if (!empty(array_filter($fotoFields, fn($f) => !empty($_FILES[$f]['tmp_name'])))) {
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
}

foreach ($fotoFields as $field) {
    if (empty($_FILES[$field]['tmp_name'])) { $fotoPaths[$field] = null; continue; }

    $file     = $_FILES[$field];
    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed  = ['jpg','jpeg','png','webp','heic','heif'];
    if (!in_array($ext, $allowed)) { $fotoPaths[$field] = null; continue; }
    if ($file['size'] > 10 * 1024 * 1024) { $fotoPaths[$field] = null; continue; } // max 10MB

    $filename = $field . '.' . $ext;
    $dest     = $uploadDir . $filename;
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        $fotoPaths[$field] = 'uploads/fotos_ivms/' . $codigo . '/' . $filename;
    } else {
        $fotoPaths[$field] = null;
    }
}

// ── Insertar registro ─────────────────────────────────────
$sql = "INSERT INTO preregistro_ivms (
    codigo_registro, placa, codigo_camion, marca, modelo, anio, flota, pais_iso,
    imei_galileosky, chip_galileosky, imei_mdvr, chip_mdvr,
    imei_iridium, chip_tablet, mac_sensor_angulo,
    adas_altura_camara, adas_ancho_vehiculo,
    adas_camara_parachoque, adas_camara_eje, adas_camera_center,
    foto_imei_galileosky, foto_chip_galileosky, foto_imei_mdvr, foto_chip_mdvr,
    foto_imei_iridium, foto_chip_tablet, foto_imei_tablet, foto_mac_sensor_angulo, foto_vehiculo,
    registrado_por, ip_origen, observaciones, estado
) VALUES (
    :codigo_registro, :placa, :codigo_camion, :marca, :modelo, :anio, :flota, :pais_iso,
    :imei_galileosky, :chip_galileosky, :imei_mdvr, :chip_mdvr,
    :imei_iridium, :chip_tablet, :mac_sensor_angulo,
    :adas_altura_camara, :adas_ancho_vehiculo,
    :adas_camara_parachoque, :adas_camara_eje, :adas_camera_center,
    :foto_imei_galileosky, :foto_chip_galileosky, :foto_imei_mdvr, :foto_chip_mdvr,
    :foto_imei_iridium, :foto_chip_tablet, :foto_imei_tablet, :foto_mac_sensor_angulo, :foto_vehiculo,
    :registrado_por, :ip_origen, :observaciones, 'pendiente'
)";

$pdo->prepare($sql)->execute([
    ':codigo_registro'         => $codigo,
    ':placa'                   => strtoupper(s($data['placa'], 20)),
    ':codigo_camion'           => s($data['codigo_camion']??null, 50),
    ':marca'                   => s($data['marca'], 80),
    ':modelo'                  => s($data['modelo'], 80),
    ':anio'                    => si($data['anio']??null),
    ':flota'                   => s($data['flota']??null, 120),
    ':pais_iso'                => s($data['pais_iso']??null, 3),
    ':imei_galileosky'         => s($data['imei_galileosky']??null, 20),
    ':chip_galileosky'         => s($data['chip_galileosky']??null, 30),
    ':imei_mdvr'               => s($data['imei_mdvr']??null, 20),
    ':chip_mdvr'               => s($data['chip_mdvr']??null, 30),
    ':imei_iridium'            => s($data['imei_iridium']??null, 20),
    ':chip_tablet'             => s($data['chip_tablet']??null, 30),
    ':mac_sensor_angulo'       => s($data['mac_sensor_angulo']??null, 20),
    ':adas_altura_camara'      => sf($data['adas_altura_camara']??null),
    ':adas_ancho_vehiculo'     => sf($data['adas_ancho_vehiculo']??null),
    ':adas_camara_parachoque'  => sf($data['adas_camara_parachoque']??null),
    ':adas_camara_eje'         => sf($data['adas_camara_eje']??null),
    ':adas_camera_center'      => sf($data['adas_camera_center']??'0'),
    ':foto_imei_galileosky'    => $fotoPaths['foto_imei_galileosky'],
    ':foto_chip_galileosky'    => $fotoPaths['foto_chip_galileosky'],
    ':foto_imei_mdvr'          => $fotoPaths['foto_imei_mdvr'],
    ':foto_chip_mdvr'          => $fotoPaths['foto_chip_mdvr'],
    ':foto_imei_iridium'       => $fotoPaths['foto_imei_iridium'],
    ':foto_chip_tablet'        => $fotoPaths['foto_chip_tablet'],
    ':foto_imei_tablet'        => $fotoPaths['foto_imei_tablet'],
    ':foto_mac_sensor_angulo'  => $fotoPaths['foto_mac_sensor_angulo'],
    ':foto_vehiculo'           => $fotoPaths['foto_vehiculo'],
    ':registrado_por'          => s($data['registrado_por']??null, 120),
    ':ip_origen'               => $_SERVER['REMOTE_ADDR'] ?? null,
    ':observaciones'           => s($data['observaciones']??null, 1000),
]);

// ── Enviar email de notificación ──────────────────────────
$pais_nombre = ['CL'=>'Chile','PE'=>'Perú','BR'=>'Brasil'][$data['pais_iso']??''] ?? '—';
$fotos_count = count(array_filter($fotoPaths));

$to      = 'centrodecontrol@gpsphonnex.com';
$subject = "[IVMS] Nuevo pre-registro: {$data['placa']} — {$pais_nombre}";

$body = "
<!DOCTYPE html>
<html>
<head><meta charset='utf-8'></head>
<body style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;color:#3D3D3D'>
  <div style='background:#3D3D3D;padding:20px;border-radius:8px 8px 0 0;border-bottom:3px solid #009BDB'>
    <h2 style='color:white;margin:0;font-size:18px'>🚛 Nuevo Pre-Registro IVMS</h2>
    <p style='color:#9CA3AF;margin:4px 0 0;font-size:13px'>GPSPhonnex Safe Tracking</p>
  </div>
  <div style='background:#f9fafb;padding:20px;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 8px 8px'>
    <table style='width:100%;border-collapse:collapse'>
      <tr><td style='padding:8px;font-weight:bold;width:40%'>Código:</td><td style='padding:8px;font-family:monospace;color:#009BDB'>{$codigo}</td></tr>
      <tr style='background:white'><td style='padding:8px;font-weight:bold'>Placa:</td><td style='padding:8px;font-family:monospace;font-weight:bold'>{$data['placa']}</td></tr>
      <tr><td style='padding:8px;font-weight:bold'>País:</td><td style='padding:8px'>{$pais_nombre}</td></tr>
      <tr style='background:white'><td style='padding:8px;font-weight:bold'>Marca / Modelo:</td><td style='padding:8px'>{$data['marca']} {$data['modelo']}</td></tr>
      <tr><td style='padding:8px;font-weight:bold'>Flota:</td><td style='padding:8px'>" . ($data['flota']??'—') . "</td></tr>
      <tr style='background:white'><td style='padding:8px;font-weight:bold'>IMEI Galileosky:</td><td style='padding:8px;font-family:monospace'>" . ($data['imei_galileosky']??'—') . "</td></tr>
      <tr><td style='padding:8px;font-weight:bold'>IMEI MDVR:</td><td style='padding:8px;font-family:monospace'>" . ($data['imei_mdvr']??'—') . "</td></tr>
      <tr style='background:white'><td style='padding:8px;font-weight:bold'>Registrado por:</td><td style='padding:8px'>" . ($data['registrado_por']??'—') . "</td></tr>
      <tr><td style='padding:8px;font-weight:bold'>Fotos adjuntas:</td><td style='padding:8px'>{$fotos_count} de 6</td></tr>
      <tr style='background:white'><td style='padding:8px;font-weight:bold'>Fecha:</td><td style='padding:8px'>" . date('d/m/Y H:i') . "</td></tr>
    </table>
    <div style='margin-top:16px;padding:12px;background:#E5F5FC;border-radius:6px;border-left:4px solid #009BDB'>
      <p style='margin:0;font-size:13px;color:#0078AA'>
        ⚡ Ingrese al panel de administración para revisar y completar el registro.
      </p>
    </div>
  </div>
</body>
</html>
";

$headers  = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=utf-8\r\n";
$headers .= "From: IVMS Pre-Registro <noreply@gpsphonnex.com>\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

@mail($to, $subject, $body, $headers);

echo json_encode([
    'ok'              => true,
    'codigo_registro' => $codigo,
    'fotos_guardadas' => $fotos_count,
    'message'         => 'Pre-registro guardado correctamente',
]);
