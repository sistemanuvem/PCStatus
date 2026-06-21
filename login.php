<?php
require_once __DIR__ . '/auth.php';

if (!empty($_SESSION['pcstatus_auth'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $pass     = $_POST['password'] ?? '';

    if ($username === '' || $pass === '') {
        $error = 'Preencha usuário e senha.';
    } elseif (auth_login($username, $pass)) {
        session_regenerate_id(true);
        $_SESSION['pcstatus_auth'] = true;
        $_SESSION['pcstatus_user'] = $username;
        header('Location: index.php');
        exit;
    } else {
        $error = 'Usuário ou senha incorretos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PC Status — Login</title>
<link rel="icon" type="image/webp" href="icone.webp">
<style>
:root{--bg:#0d1117;--card:#161b22;--border:#30363d;--text:#c9d1d9;
  --dim:#8b949e;--accent:#58a6ff;--ok:#3fb950;--danger:#f85149}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Consolas','Courier New',monospace;background:var(--bg);
  color:var(--text);min-height:100vh;display:flex;flex-direction:column;
  align-items:center;justify-content:center;padding:20px;gap:16px}

.login-box{background:var(--card);border:1px solid var(--border);
  border-radius:10px;padding:36px 40px;width:100%;max-width:360px}

.logo{text-align:center;margin-bottom:24px}
.logo h1{color:var(--accent);font-size:1.2rem;letter-spacing:.04em;margin-bottom:4px}
.logo p{color:var(--dim);font-size:.78rem}

label{display:block;font-size:.8rem;color:var(--dim);margin-bottom:5px}
input[type="text"],input[type="password"]{
  width:100%;background:#0d1117;border:1px solid var(--border);
  border-radius:6px;color:var(--text);font-family:inherit;
  font-size:.9rem;padding:10px 12px;margin-bottom:14px;
  outline:none;transition:border-color .2s}
input:focus{border-color:var(--accent)}

.btn{width:100%;background:#238636;color:#fff;border:none;
  border-radius:6px;font-family:inherit;font-size:.95rem;font-weight:bold;
  padding:11px;cursor:pointer;margin-top:4px;transition:background .2s}
.btn:hover{background:#2ea043}

.error{background:#2d1b1b;border:1px solid var(--danger);color:var(--danger);
  border-radius:6px;padding:10px 14px;font-size:.82rem;margin-bottom:16px}

.hint{color:var(--dim);font-size:.72rem;text-align:center;margin-top:8px}
</style>
</head>
<body>
<div class="login-box">
  <div class="logo">
    <img src="icone.webp" alt="PC Status" style="width:60px;height:60px;margin-bottom:14px;display:block;margin-left:auto;margin-right:auto">
    <h1>PC Status Monitor</h1>
    <p>Monitoramento de sistemas</p>
  </div>

  <?php if ($error !== ''): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" action="" autocomplete="on">
    <label for="u">Usuário</label>
    <input type="text" id="u" name="username"
           value="<?= htmlspecialchars($username) ?>"
           autocomplete="username" autofocus required>

    <label for="p">Senha</label>
    <input type="password" id="p" name="password"
           autocomplete="current-password" required>

    <button type="submit" class="btn">Entrar</button>
  </form>
  <p class="hint">Padrão: admin / admin</p>
</div>
</body>
</html>
