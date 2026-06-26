<?php
/**
 * GPSP — API Listar / Actualizar / Archivar pre-registros
 * Archivo: get_registros.php
 * Versión: V3.0 | 2026-06-22
 *
 * GET  ?action=list              → lista registros según rol
 * GET  ?action=list&archivados=1 → lista archivados (solo admin)
 * POST action=update             → actualiza un registro
 * POST action=archivar           → archiva un registro (solo admin)
 */
require_once __DIR__ . '/config.php';
setCorsHeaders();

$pdo    = dbConnect();
$method = $_SERVER['REQUEST_METHOD'];
$data   = [];

if ($method === 'POST') {
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true) ?? [];
}

$action = $_GET['action'] ?? ($data['action'] ?? '');

// ── GET list ──────────────────────────────────────────────
if ($method === 'GET' && $action === 'list') {
    $user_id    = trim($_GET['user_id']   ?? '');
    $user_role  = trim($_GET['user_role'] ?? '');
    $archivados = ($_GET['archivados'] ?? '0') === '1';

    $estadoWhere = $archivados ? "estado = 'archivado'" : "estado != 'archivado'";

    $userPaisMap = json_decode(USER_PAIS, true);
    $pais = $userPaisMap[$user_id] ?? null;

    if ($user_role === 'admin') {
        $stmt = $pdo->prepare("SELECT * FROM preregistro_ivms WHERE $estadoWhere ORDER BY created_at DESC");
        $stmt->execute();
    } elseif ($pais) {
        $stmt = $pdo->prepare("SELECT * FROM preregistro_ivms WHERE pais_iso = ? AND $estadoWhere ORDER BY created_at DESC");
        $stmt->execute([$pais]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM preregistro_ivms WHERE registrado_por = ? AND $estadoWhere ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
    }

    echo json_encode(['ok'=>true, 'records'=>$stmt->fetchAll()]);
    exit;
}

// helpers
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

// ── POST update ───────────────────────────────────────────
if ($method === 'POST' && $action === 'update') {
    $id = (int)($data['id'] ?? 0);
    if (!$id) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'ID requerido']); exit; }

    $sql = "UPDATE preregistro_ivms SET
        placa=:placa, codigo_camion=:codigo_camion, marca=:marca, modelo=:modelo,
        anio=:anio, flota=:flota,
        imei_galileosky=:imei_galileosky, chip_galileosky=:chip_galileosky,
        imei_mdvr=:imei_mdvr, chip_mdvr=:chip_mdvr,
        imei_iridium=:imei_iridium, chip_tablet=:chip_tablet,
        mac_sensor_angulo=:mac_sensor_angulo,
        adas_altura_camara=:adas_altura_camara, adas_ancho_vehiculo=:adas_ancho_vehiculo,
        adas_camara_parachoque=:adas_camara_parachoque, adas_camara_eje=:adas_camara_eje,
        adas_camera_center=:adas_camera_center,
        estado=:estado, observaciones=:observaciones, updated_at=NOW()
        WHERE id=:id";

    $pdo->prepare($sql)->execute([
        ':placa'               => strtoupper(s($data['placa']??null, 20)),
        ':codigo_camion'       => s($data['codigo_camion']??null, 50),
        ':marca'               => s($data['marca']??null, 80),
        ':modelo'              => s($data['modelo']??null, 80),
        ':anio'                => si($data['anio']??null),
        ':flota'               => s($data['flota']??null, 120),
        ':imei_galileosky'     => s($data['imei_galileosky']??null, 20),
        ':chip_galileosky'     => s($data['chip_galileosky']??null, 30),
        ':imei_mdvr'           => s($data['imei_mdvr']??null, 20),
        ':chip_mdvr'           => s($data['chip_mdvr']??null, 30),
        ':imei_iridium'        => s($data['imei_iridium']??null, 20),
        ':chip_tablet'         => s($data['chip_tablet']??null, 30),
        ':mac_sensor_angulo'   => s($data['mac_sensor_angulo']??null, 20),
        ':adas_altura_camara'  => sf($data['adas_altura_camara']??null),
        ':adas_ancho_vehiculo' => sf($data['adas_ancho_vehiculo']??null),
        ':adas_camara_parachoque' => sf($data['adas_camara_parachoque']??null),
        ':adas_camara_eje'     => sf($data['adas_camara_eje']??null),
        ':adas_camera_center'  => sf($data['adas_camera_center']??null),
        ':estado'              => s($data['estado']??'pendiente', 20),
        ':observaciones'       => s($data['observaciones']??null, 1000),
        ':id'                  => $id,
    ]);
    echo json_encode(['ok'=>true,'message'=>'Registro actualizado']);
    exit;
}

// ── POST archivar ─────────────────────────────────────────
if ($method === 'POST' && $action === 'archivar') {
    $id        = (int)($data['id'] ?? 0);
    $user_role = trim($data['user_role'] ?? '');
    if ($user_role !== 'admin') {
        http_response_code(403);
        echo json_encode(['ok'=>false,'error'=>'Solo el administrador puede archivar registros']);
        exit;
    }
    if (!$id) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'ID requerido']); exit; }
    $pdo->prepare("UPDATE preregistro_ivms SET estado='archivado', updated_at=NOW() WHERE id=?")->execute([$id]);
    echo json_encode(['ok'=>true,'message'=>'Registro archivado']);
    exit;
}

http_response_code(400);
echo json_encode(['ok'=>false,'error'=>'Acción no reconocida']);
