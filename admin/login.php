<?php
require dirname(__DIR__) . '/app/bootstrap.php';
if (is_admin()) { header('Location: ' . base_url('admin/index.php')); exit; }
$error = '';
$notice = (string)($_SESSION['auth_notice'] ?? ''); unset($_SESSION['auth_notice']);
$email = strtolower(trim((string)($_POST['email'] ?? '')));
$ipHash = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

// Keep the rate limiter in MySQL so creating a new browser session does not reset it.
try {
    $pdo->query('SELECT 1 FROM login_attempts LIMIT 1');
} catch (Throwable $exception) {
    try { $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, ip_hash CHAR(64) NOT NULL, email VARCHAR(190) NOT NULL, attempted_at DATETIME NOT NULL, INDEX idx_login_ip_time (ip_hash, attempted_at), INDEX idx_login_email_time (email, attempted_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Throwable $createException) { error_log('NOWRD login rate limiter unavailable: ' . $createException->getMessage()); }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $locked = false;
    try {
        $rate = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE attempted_at >= (NOW() - INTERVAL 15 MINUTE) AND (ip_hash = ? OR email = ?)');
        $rate->execute([$ipHash, $email]);
        $locked = (int)$rate->fetchColumn() >= 5;
    } catch (Throwable $exception) { /* continue login if an old DB cannot create the optional limiter */ }

    if ($locked) {
        http_response_code(429);
        $error = 'Terlalu banyak percobaan login. Tunggu 15 menit sebelum mencoba kembali.';
    } else {
        $query = $pdo->prepare('SELECT id, name, email, password_hash FROM users WHERE email = ? LIMIT 1');
        $query->execute([$email]);
        $user = $query->fetch();
        if ($user && password_verify((string)($_POST['password'] ?? ''), $user['password_hash'])) {
            // Upgrade older password hashes after a successful login, without changing the user's password.
            if (password_needs_rehash((string)$user['password_hash'], PASSWORD_DEFAULT)) {
                try { $rehash = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?'); $rehash->execute([password_hash((string)($_POST['password'] ?? ''), PASSWORD_DEFAULT), (int)$user['id']]); } catch (Throwable $e) { error_log('NOWRD password hash upgrade failed: ' . $e->getMessage()); }
            }
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$user['id'];
            $_SESSION['admin_name'] = $user['name'];
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
            $_SESSION['_last_activity'] = time();
            nowrd_audit('auth.login_succeeded','admin',(int)$user['id'],'Administrator login succeeded');
            try { $pdo->prepare('DELETE FROM login_attempts WHERE ip_hash = ? OR email = ?')->execute([$ipHash, $email]); } catch (Throwable $exception) {}
            header('Location: ' . base_url('admin/index.php'));
            exit;
        }
        try { $pdo->prepare('INSERT INTO login_attempts(ip_hash,email,attempted_at) VALUES(?,?,NOW())')->execute([$ipHash, $email]); } catch (Throwable $exception) {}
        nowrd_audit('auth.login_failed','admin',null,'Failed login attempt');
        $error = 'Email atau password tidak cocok.';
    }
}
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Sign in — NOWRD Studio</title><link rel="stylesheet" href="<?=e(base_url('public/assets/style.css'))?>"></head><body class="auth-body"><a class="brand auth-brand" href="<?=e(base_url('index.php'))?>"><span class="brand-mark">n.</span> NOWRD</a><main class="auth-card"><div class="eyebrow">NOWRD STUDIO</div><h1>Welcome<br><em>back.</em></h1><p>Your quiet corner of the internet.</p><?php if($notice): ?><div class="alert" role="status"><?=e($notice)?></div><?php endif; ?><?php if($error): ?><div class="alert" role="alert"><?=e($error)?></div><?php endif; ?><form method="post"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><label for="email">Email address</label><input id="email" type="email" name="email" value="<?=e($email)?>" required autocomplete="username" maxlength="190" placeholder="you@example.com"><label for="password">Password</label><input id="password" type="password" name="password" required autocomplete="current-password" maxlength="1024" placeholder="Your password"><button class="button full" type="submit">Enter the studio <span>↗</span></button></form><a class="muted-link" href="<?=e(base_url('index.php'))?>">← Back to website</a></main></body></html>
