<?php
/**
 * Autenticacao por sessao PHP.
 * Credenciais padrão: admin / admin
 * Para sobrescrever, crie data/auth.json com { "username":"...", "password":"..." }
 * ou com hash bcrypt: { "username":"...", "password_hash":"$2y$10$..." }
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict']);
    session_start();
}

function _auth_creds(): array {
    $f = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'auth.json';
    if (is_file($f)) {
        $d = json_decode(@file_get_contents($f), true);
        if (is_array($d) && !empty($d['username'])) return $d;
    }
    return ['username' => 'admin', 'password' => 'admin'];
}

/** Redireciona para login.php se não autenticado. */
function auth_check(): void {
    if (empty($_SESSION['pcstatus_auth'])) {
        header('Location: login.php');
        exit;
    }
}

/** Devolve JSON 401 se não autenticado (para endpoints de API). */
function auth_check_api(): void {
    if (empty($_SESSION['pcstatus_auth'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        exit(json_encode(['error' => 'Unauthorized']));
    }
}

/** Valida usuario + senha. Retorna true se correto. */
function auth_login(string $user, string $pass): bool {
    $creds = _auth_creds();
    if ($user !== ($creds['username'] ?? 'admin')) return false;

    if (!empty($creds['password_hash'])) {
        return password_verify($pass, $creds['password_hash']);
    }
    return $pass === ($creds['password'] ?? 'admin');
}

/** Encerra a sessão. */
function auth_logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 86400,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Retorna o nome do usuario logado. */
function auth_user(): string {
    return $_SESSION['pcstatus_user'] ?? 'admin';
}
