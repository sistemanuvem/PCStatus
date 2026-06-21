<?php
require_once __DIR__ . '/auth.php';
auth_check();

$logged_user = auth_user();
$success = '';
$error   = '';

// Lê config atual
$auth_file  = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'auth.json';
$has_custom = is_file($auth_file);
$cfg        = $has_custom ? (json_decode(file_get_contents($auth_file), true) ?? []) : [];
$cur_user   = $cfg['username'] ?? 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'change_credentials') {
        $new_user  = trim($_POST['username'] ?? '');
        $cur_pass  = $_POST['current_password'] ?? '';
        $new_pass  = $_POST['password'] ?? '';
        $conf_pass = $_POST['confirm'] ?? '';

        if (!auth_login($logged_user, $cur_pass)) {
            $error = 'Senha atual incorreta.';
        } elseif ($new_user === '') {
            $error = 'Usuário não pode ser vazio.';
        } elseif (strlen($new_pass) < 4) {
            $error = 'Nova senha deve ter pelo menos 4 caracteres.';
        } elseif ($new_pass !== $conf_pass) {
            $error = 'As senhas não coincidem.';
        } else {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $data = ['username' => $new_user, 'password_hash' => $hash];
            if (!is_dir(dirname($auth_file))) mkdir(dirname($auth_file), 0755, true);

            $tmp = $auth_file . '.tmp';
            if (file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) !== false
                && rename($tmp, $auth_file)) {
                $_SESSION['pcstatus_user'] = $new_user;
                $logged_user = $new_user;
                $cur_user    = $new_user;
                $has_custom  = true;
                $success     = 'Credenciais atualizadas com sucesso!';
            } else {
                $error = 'Erro ao salvar. Verifique permissões da pasta data/.';
            }
        }

    } elseif ($action === 'reset_defaults') {
        $cur_pass = $_POST['current_password_reset'] ?? '';
        if (!auth_login($logged_user, $cur_pass)) {
            $error = 'Senha atual incorreta.';
        } else {
            @unlink($auth_file);
            $_SESSION['pcstatus_user'] = 'admin';
            $logged_user = 'admin';
            $cur_user    = 'admin';
            $has_custom  = false;
            $success     = 'Configurações redefinidas para admin / admin.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PC Status — Configurações</title>
<link rel="icon" type="image/webp" href="icone.webp">
<style>
:root{--bg:#0d1117;--card:#161b22;--border:#30363d;--text:#c9d1d9;
  --dim:#8b949e;--accent:#58a6ff;--ok:#3fb950;--warn:#d29922;--danger:#f85149}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Consolas','Courier New',monospace;background:var(--bg);
  color:var(--text);min-height:100vh;padding:20px}

/* topo */
.top-bar{display:flex;justify-content:space-between;align-items:center;margin:0 auto 24px;gap:12px;max-width:480px}
.top-bar h1{color:var(--accent);font-size:1.3rem}
.top-links{display:flex;gap:10px;align-items:center;flex-shrink:0}
a.btn-nav{font-family:inherit;font-size:.78rem;color:var(--dim);text-decoration:none;
  padding:4px 10px;border:1px solid var(--border);border-radius:5px;
  transition:border-color .2s,color .2s;white-space:nowrap}
a.btn-nav:hover{border-color:var(--accent);color:var(--accent)}
a.btn-danger:hover{border-color:var(--danger);color:var(--danger)}

/* layout */
.page{max-width:480px;display:flex;flex-direction:column;gap:16px;margin:0 auto}
.card{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:24px}
.card-title{font-size:.8rem;color:var(--accent);letter-spacing:.08em;
  text-transform:uppercase;margin-bottom:16px;padding-bottom:8px;
  border-bottom:1px solid var(--border)}

/* status badge */
.badge{display:inline-block;font-size:.72rem;padding:2px 8px;border-radius:10px;margin-left:8px;vertical-align:middle}
.badge-default{background:#21262d;color:var(--warn);border:1px solid var(--warn)}
.badge-custom {background:#1a2d1a;color:var(--ok); border:1px solid var(--ok)}

.info-row{font-size:.82rem;color:var(--dim);margin-bottom:16px}
.info-row strong{color:var(--text)}

/* form */
label{display:block;font-size:.8rem;color:var(--dim);margin-bottom:5px}
input[type="text"],input[type="password"]{
  width:100%;background:#0d1117;border:1px solid var(--border);
  border-radius:6px;color:var(--text);font-family:inherit;
  font-size:.88rem;padding:9px 12px;margin-bottom:14px;
  outline:none;transition:border-color .2s}
input:focus{border-color:var(--accent)}
input:last-of-type{margin-bottom:0}

.btn{width:100%;border:none;border-radius:6px;font-family:inherit;
  font-size:.92rem;font-weight:bold;padding:10px;cursor:pointer;
  margin-top:14px;transition:background .2s}
.btn-green{background:#238636;color:#fff}.btn-green:hover{background:#2ea043}
.btn-red  {background:#6e1a1a;color:#fff;border:1px solid var(--danger)}
.btn-red:hover{background:#8b2020}

.msg{border-radius:6px;padding:10px 14px;font-size:.82rem;margin-bottom:14px}
.msg-ok   {background:#1a2d1a;border:1px solid var(--ok);  color:var(--ok)}
.msg-err  {background:#2d1b1b;border:1px solid var(--danger);color:var(--danger)}

/* hash preview */
.hash-box{background:#0d1117;border:1px solid var(--border);border-radius:6px;
  padding:10px 12px;font-size:.72rem;color:var(--dim);word-break:break-all;
  margin-top:8px;display:none}
</style>
</head>
<body>

<div class="top-bar">
  <h1>Configuracoes</h1>
  <div class="top-links">
    <a href="index.php" class="btn-nav">← Dashboard</a>
    <a href="logout.php" class="btn-nav btn-danger">Sair</a>
  </div>
</div>

<div class="page">

  <?php if ($success): ?>
    <div class="msg msg-ok"><?= htmlspecialchars($success) ?></div>
  <?php elseif ($error): ?>
    <div class="msg msg-err"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Status atual -->
  <div class="card">
    <div class="card-title">
      Status das credenciais
      <?php if ($has_custom): ?>
        <span class="badge badge-custom">personalizado</span>
      <?php else: ?>
        <span class="badge badge-default">padrao</span>
      <?php endif; ?>
    </div>
    <div class="info-row">Usuário atual: <strong><?= htmlspecialchars($cur_user) ?></strong></div>
    <div class="info-row">
      Arquivo: <strong>data/auth.json</strong><br>
      <?php if ($has_custom): ?>
        Senha armazenada como <strong>hash bcrypt</strong>.
      <?php else: ?>
        Arquivo nao encontrado — usando padrao <strong>admin / admin</strong>.
      <?php endif; ?>
    </div>
  </div>

  <!-- Alterar credenciais -->
  <div class="card">
    <div class="card-title">Alterar usuario e senha</div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="change_credentials">

      <label>Senha atual</label>
      <input type="password" name="current_password" autocomplete="current-password" required>

      <label>Novo usuário</label>
      <input type="text" name="username"
             value="<?= htmlspecialchars($cur_user) ?>"
             autocomplete="username" required>

      <label>Nova senha</label>
      <input type="password" name="password"
             id="new-pass" autocomplete="new-password"
             oninput="showHash(this.value)" required>

      <label>Confirmar nova senha</label>
      <input type="password" name="confirm" autocomplete="new-password" required>

      <div class="hash-box" id="hash-preview">
        Hash bcrypt que sera salvo:<br><span id="hash-val"></span>
      </div>

      <button type="submit" class="btn btn-green">Salvar</button>
    </form>
  </div>

  <!-- Redefinir para padrao -->
  <?php if ($has_custom): ?>
  <div class="card">
    <div class="card-title">Redefinir para padrao</div>
    <div class="info-row">Remove <strong>data/auth.json</strong> e volta para <strong>admin / admin</strong>.</div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="reset_defaults">
      <label>Confirme a senha atual</label>
      <input type="password" name="current_password_reset" autocomplete="current-password" required>
      <button type="submit" class="btn btn-red">Redefinir para admin / admin</button>
    </form>
  </div>
  <?php endif; ?>

  <!-- Gerador de hash avulso -->
  <div class="card">
    <div class="card-title">Gerar hash bcrypt manualmente</div>
    <div class="info-row">Cole o hash gerado em <strong>data/auth.json</strong> no campo <code>password_hash</code>.</div>
    <form method="POST" action="hash.php" target="hash-frame">
      <label>Senha para gerar hash</label>
      <input type="password" name="plain" id="manual-plain" required>
      <button type="submit" class="btn btn-green" style="background:#1f3a5f;color:var(--accent);border:1px solid var(--accent)"
              onclick="this.form.target='hash-frame'">Gerar hash</button>
    </form>
    <iframe name="hash-frame" id="hash-frame"
            style="width:100%;border:none;background:transparent;margin-top:10px;height:0"
            onload="frameLoaded(this)"></iframe>
  </div>

</div>

<script>
// Preview do hash avulso via iframe
function frameLoaded(f){
  try{
    const txt = f.contentDocument?.body?.innerText?.trim();
    if(txt && txt.startsWith('$2')){
      f.style.height='auto';
      f.contentDocument.body.style.cssText =
        'margin:0;padding:10px 12px;font-family:Consolas,monospace;'+
        'font-size:.72rem;background:#0d1117;color:#8b949e;word-break:break-all;'+
        'border:1px solid #30363d;border-radius:6px;margin-top:8px';
    }
  } catch(e){}
}
</script>

</body>
</html>
