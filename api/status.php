<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once dirname(__DIR__) . '/auth.php';
auth_check_api();
require_once __DIR__ . '/cache.php';

$pcs = pcstatus_fetch_all();
echo json_encode(
    ['pcs' => $pcs ?: new stdClass()],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
);
