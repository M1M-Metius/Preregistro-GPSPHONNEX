<?php
/**
 * GPSP — Endpoint de Login
 * Archivo: auth_login.php
 * Versión: V3.0 | 2026-06-22
 */
require_once __DIR__ . '/config.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'Método no permitido']);
    exit;
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
$user = trim($data['usuario']  ?? '');
$pass = trim($data['password'] ?? '');

if (!$user || !$pass) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>'Usuario y contraseña requeridos']);
    exit;
}

$pdo = dbConnect();

$stmt = $pdo->prepare(
    "SELECT id_identi_user, id_usua, no_nombre, n_pass, us_tipo,
            id_emp, failed_attempts, locked_until, email
     FROM usuario
     WHERE (id_usua = :u OR email = :u2)
     LIMIT 1"
);
$stmt->execute([':u' => $user, ':u2' => $user]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'Credenciales incorrectas']);
    exit;
}

if ($row['locked_until'] && strtotime($row['locked_until']) > time()) {
    $restantes = ceil((strtotime($row['locked_until']) - time()) / 60);
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>"Cuenta bloqueada. Intente en {$restantes} minutos."]);
    exit;
}

if (ALLOWED_ID_EMP !== '' && $row['id_emp'] !== ALLOWED_ID_EMP) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'Acceso no autorizado para esta empresa']);
    exit;
}

$passOk = false;
if (password_verify($pass, $row['n_pass'])) {
    $passOk = true;
} elseif ($row['n_pass'] === md5($pass)) {
    $passOk = true;
    $pdo->prepare("UPDATE usuario SET n_pass=? WHERE id_identi_user=?")
        ->execute([password_hash($pass, PASSWORD_DEFAULT), $row['id_identi_user']]);
}

if (!$passOk) {
    $failed = (int)$row['failed_attempts'] + 1;
    if ($failed >= MAX_FAILED) {
        $lock = date('Y-m-d H:i:s', strtotime('+'.LOCK_MINUTES.' minutes'));
        $pdo->prepare("UPDATE usuario SET failed_attempts=?, locked_until=? WHERE id_identi_user=?")
            ->execute([$failed, $lock, $row['id_identi_user']]);
        http_response_code(403);
        echo json_encode(['ok'=>false,'error'=>"Demasiados intentos. Bloqueado por ".LOCK_MINUTES." minutos."]);
    } else {
        $pdo->prepare("UPDATE usuario SET failed_attempts=? WHERE id_identi_user=?")
            ->execute([$failed, $row['id_identi_user']]);
        http_response_code(401);
        echo json_encode(['ok'=>false,'error'=>'Credenciales incorrectas. Intento '.$failed.' de '.MAX_FAILED]);
    }
    exit;
}

$pdo->prepare("UPDATE usuario SET failed_attempts=0, locked_until=NULL, ult_login=NOW() WHERE id_identi_user=?")
    ->execute([$row['id_identi_user']]);

$roleMap = [1=>'admin', 2=>'admin', 3=>'tecnico', 4=>'cliente'];
$role = $roleMap[(int)$row['us_tipo']] ?? 'tecnico';

echo json_encode([
    'ok'    => true,
    'token' => bin2hex(random_bytes(24)),
    'user'  => [
        'id'    => $row['id_usua'],
        'name'  => $row['no_nombre'] ?: $row['id_usua'],
        'email' => $row['email'],
        'role'  => $role,
        'emp'   => $row['id_emp'],
    ],
]);
