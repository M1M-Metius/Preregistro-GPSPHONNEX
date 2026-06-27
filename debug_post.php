<?php
// Archivo temporal de debug — eliminar después de diagnosticar
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Auth-Token');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

echo json_encode([
    'post_fields'    => array_keys($_POST),
    'post_placa'     => $_POST['placa'] ?? 'NO RECIBIDO',
    'post_marca'     => $_POST['marca'] ?? 'NO RECIBIDO',
    'files_received' => array_keys($_FILES),
    'content_type'   => $_SERVER['CONTENT_TYPE'] ?? '',
    'content_length' => $_SERVER['CONTENT_LENGTH'] ?? '',
    'php_post_max'   => ini_get('post_max_size'),
    'php_upload_max' => ini_get('upload_max_filesize'),
    'php_version'    => PHP_VERSION,
]);
