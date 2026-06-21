<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/cache.php';

define('API_KEY', 'pcstatus-key-changeme');

if (($_SERVER['HTTP_X_API_KEY'] ?? '') !== API_KEY) {
    http_response_code(401);
    exit(json_encode(['error' => 'Unauthorized']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method Not Allowed']));
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid JSON']));
}

$data['received_at'] = date('c');
$pc_name = trim($data['pc_name'] ?? 'unknown');

if (pcstatus_store($pc_name, $data)) {
    echo json_encode(['ok' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Falha ao gravar', 'dir' => PCSTATUS_DATA_DIR]);
}
