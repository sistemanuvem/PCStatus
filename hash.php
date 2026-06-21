<?php
/**
 * Gera um hash bcrypt para uso em data/auth.json.
 * Acessado via iframe em settings.php — sem template HTML.
 */
require_once __DIR__ . '/auth.php';
auth_check();

header('Content-Type: text/plain; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$plain = $_POST['plain'] ?? '';
if (strlen($plain) < 1) {
    http_response_code(400);
    exit('Senha vazia.');
}
if (strlen($plain) > 72) {
    http_response_code(400);
    exit('Senha muito longa (max 72 chars para bcrypt).');
}

echo password_hash($plain, PASSWORD_DEFAULT);
