<?php
require dirname(__DIR__) . '/app/bootstrap.php';
require_permission('users.manage');
$error = '';
$notice = '';
$currentId = (int)($_SESSION['admin_id'] ?? 0);
ensure_role_schema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'change_password') {
            $current = (string)($_POST['current_password'] ?? '');
            $new = (string)($_POST['new_password'] ?? '');
            $confirm = (string)($_POST['confirm_password'] ?? '');
            $q = $pdo->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
            $q->execute([$currentId]);
            $hash = (string)$q->fetchColumn();
            if ($hash === '' || !password_verify($current, $hash)) throw new RuntimeException('Kata sandi saat ini tidak cocok.');
            if (strlen($new) < 12) throw new RuntimeException('Kata sandi baru harus minimal 12 karakter.');
            if ($new !== $confirm) throw new RuntimeException('Konfirmasi kata sandi tidak sama.');
            if (hash_equals($current, $new)) throw new RuntimeException('Gunakan kata sandi baru yang berbeda.');
            $q = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $q->execute([password_hash($new, PASSWORD_DEFAULT), $currentId]);
            session_regenerate_id(true);
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
            nowrd_audit('admin.password_changed', 'admin', $currentId, 'Administrator changed own password');
            $notice = 'Kata sandi berhasil diperbarui. Simpan kata sandi baru di tempat yang aman.';
        } elseif ($action === 'create_admin') {
            $name = trim((string)($_POST['name'] ?? ''));
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $password = (string)($_POST['password'] ?? '');
            $role = (string)($_POST['role'] ?? 'author');
            if (!in_array($role, ['administrator','editor','author'], true)) throw new RuntimeException('Peran akun tidak valid.');
            if ($name === '' || text_length($name) > 120) throw new RuntimeException('Nama wajib diisi dan maksimal 120 karakter.');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) throw new RuntimeException('Masukkan alamat email yang valid.');
            if (strlen($password) < 12) throw new RuntimeException('Kata sandi admin baru harus minimal 12 karakter.');
            $q = $pdo->prepare('INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?)');
            $q->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
            $newId = (int)$pdo->lastInsertId();
            nowrd_audit('admin.account_created', 'admin', $newId, 'Account created (' . $role . '): ' . $email);
            $notice = 'Akun admin baru berhasil dibuat.';
        } elseif ($action === 'change_role') {
            $target = (int)($_POST['user_id'] ?? 0);
            $role = (string)($_POST['role'] ?? '');
            if ($target < 1 || !in_array($role, ['administrator','editor','author'], true)) throw new RuntimeException('Akun atau peran tidak valid.');
            if ($target === $currentId && $role !== 'administrator') throw new RuntimeException('Kamu tidak dapat menurunkan peran akun yang sedang digunakan.');
            $q = $pdo->prepare('SELECT id,email,role FROM users WHERE id=? LIMIT 1');
            $q->execute([$target]);
            $targetUser = $q->fetch();
            if (!$targetUser) throw new RuntimeException('Akun admin tidak ditemukan.');
            if ($targetUser['role'] === 'administrator' && $role !== 'administrator') {
                $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='administrator'")->fetchColumn();
                if ($adminCount <= 1) throw new RuntimeException('Administrator terakhir tidak dapat diturunkan perannya.');
            }
            $pdo->prepare('UPDATE users SET role=? WHERE id=?')->execute([$role, $target]);
            nowrd_audit('admin.role_changed', 'admin', $target, 'Account role changed to ' . $role . ': ' . (string)$targetUser['email']);
            $notice = 'Peran akun berhasil diperbarui.';
        } elseif ($action === 'delete_admin') {
            $target = (int)($_POST['user_id'] ?? 0);
            if ($target < 1 || $target === $currentId) throw new RuntimeException('Kamu tidak dapat menghapus akun yang sedang digunakan.');
            $count = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            if ($count <= 1) throw new RuntimeException('Akun terakhir tidak boleh dihapus.');
            $q = $pdo->prepare('SELECT id,email,role FROM users WHERE id=? LIMIT 1');
            $q->execute([$target]);
            $targetUser = $q->fetch();
            if (!$targetUser) throw new RuntimeException('Akun admin tidak ditemukan.');
            if ($targetUser['role'] === 'administrator' && (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='administrator'")->fetchColumn() <= 1) throw new RuntimeException('Administrator terakhir tidak boleh dihapus.');
            $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$target]);
            nowrd_audit('admin.account_deleted', 'admin', $target, 'Administrator account deleted: ' . (string)$targetUser['email']);
            $notice = 'Akun admin berhasil dihapus.';
        } else {
            throw new RuntimeException('Tindakan tidak dikenal.');
        }
    } catch (PDOException $e) {
        if ((string)$e->getCode() === '23000') $error = 'Email tersebut sudah digunakan oleh akun lain.';
        else { error_log('NOWRD admin account action failed: ' . $e->getMessage()); $error = 'Perubahan tidak dapat diselesaikan. Periksa konfigurasi database.'; }
    } catch (RuntimeException $e) { $error = $e->getMessage(); }
}

$admins = $pdo->query('SELECT id,name,email,role,created_at FROM users ORDER BY id ASC')->fetchAll();
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Admin Accounts — NOWRD Studio</title><link rel="stylesheet" href="<?=e(base_url('public/assets/style.css'))?>"><style>
.account-wrap{max-width:1120px;margin:0 auto;padding:32px 26px 60px}.account-grid{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(300px,.8fr);gap:18px;align-items:start}.account-card{background:var(--surface,#fff);border:1px solid var(--line);border-radius:16px;padding:22px;min-width:0}.account-card h2{margin:0 0 8px}.account-card p{color:var(--muted);line-height:1.6;font-size:13px}.account-form{display:grid;gap:13px;margin-top:18px}.account-form label{display:grid;gap:6px;font-size:12px;font-weight:700}.account-form input{width:100%;min-width:0;border:1px solid var(--line);border-radius:9px;padding:11px 12px;font:inherit;background:var(--paper,#fff);color:var(--ink)}.account-form .button{margin:4px 0 0;justify-self:start}.account-table{width:100%;border-collapse:collapse;margin-top:14px;font-size:13px}.account-table th,.account-table td{padding:12px 8px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}.account-table th{font-size:10px;letter-spacing:.08em;text-transform:uppercase;color:var(--muted)}.account-table small{display:block;color:var(--muted);margin-top:3px}.account-table form{margin:0}.account-danger{background:transparent;color:#a33;border:1px solid currentColor;border-radius:8px;padding:7px 9px;font:inherit;font-size:11px;cursor:pointer}.account-notice{padding:12px 14px;border-radius:10px;margin:12px 0;background:var(--paper,#f7f7f7);border:1px solid var(--line);font-size:13px}.account-error{border-color:#c66}.account-note{font-size:12px!important;padding:12px;background:var(--paper,#f7f7f7);border-radius:10px}.account-table .current{font-size:10px;font-weight:700;border:1px solid var(--line);padding:3px 6px;border-radius:20px;white-space:nowrap}@media(max-width:820px){.account-grid{grid-template-columns:1fr}.account-wrap{padding:22px 14px}.account-card{padding:17px}.account-table th:nth-child(4),.account-table td:nth-child(4){display:none}}
</style></head><body class="dashboard"><aside class="sidebar"><a class="brand" href="<?=e(base_url('admin/index.php'))?>"><span class="brand-mark"><?=e(setting('brand_mark','n.'))?></span> <?=e(setting('brand_name','NOWRD'))?></a><div class="side-label">WORKSPACE</div><a class="side-link" href="<?=e(base_url('admin/index.php'))?>">▦ &nbsp; Overview</a><a class="side-link" href="<?=e(base_url('admin/post.php'))?>">✎ &nbsp; New story</a><a class="side-link" href="<?=e(base_url('admin/media.php'))?>">▧ &nbsp; Media Library</a><a class="side-link" href="<?=e(base_url('admin/taxonomies.php'))?>">◇ &nbsp; Categories & Tags</a><a class="side-link" href="<?=e(base_url('admin/settings.php'))?>">⚙ &nbsp; Settings</a><a class="side-link active" href="<?=e(base_url('admin/users.php'))?>">♙ &nbsp; Admin accounts</a><a class="side-link" href="<?=e(base_url('admin/maintenance.php'))?>">⌁ &nbsp; Maintenance</a><a class="side-link" href="<?=e(base_url('index.php'))?>">↗ &nbsp; View website</a><div class="side-bottom"><div class="avatar"><?=e(strtoupper(substr($_SESSION['admin_name']??'N',0,1)))?></div><div><b><?=e($_SESSION['admin_name']??'Editor')?></b><small>Administrator</small></div></div></aside><main class="admin-main"><div class="account-wrap"><header class="admin-top"><div><div class="eyebrow">CONTROL CENTER / ACCESS</div><h1>Admin accounts<span class="lime">.</span></h1><p class="muted">Manage who can access the publication and keep account security in good shape.</p></div></header><?php if($error):?><div class="account-notice account-error" role="alert"><?=e($error)?></div><?php endif;?><?php if($notice):?><div class="account-notice" role="status"><?=e($notice)?></div><?php endif;?><div class="account-grid"><section class="account-card"><h2>People with access</h2><p>Give each person only the access their work requires. Administrator controls accounts and settings; Editors manage publication content; Authors manage their own stories.</p><div style="overflow-x:auto"><table class="account-table"><thead><tr><th>Account</th><th>Role</th><th>Created</th><th>Action</th></tr></thead><tbody><?php foreach($admins as $admin):?><tr><td><b><?=e($admin['name'])?></b><small><?=e($admin['email'])?></small><?php if((int)$admin['id']===$currentId):?> <span class="current">YOU</span><?php endif;?></td><td><form method="post" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="change_role"><input type="hidden" name="user_id" value="<?=(int)$admin['id']?>"><select name="role" aria-label="Role for <?=e($admin['email'])?>" style="max-width:140px;border:1px solid var(--line);border-radius:8px;padding:7px;background:var(--paper,#fff);color:var(--ink)" <?=((int)$admin['id']===$currentId)?'disabled':''?>><option value="administrator" <?=$admin['role']==='administrator'?'selected':''?>>Administrator</option><option value="editor" <?=$admin['role']==='editor'?'selected':''?>>Editor</option><option value="author" <?=$admin['role']==='author'?'selected':''?>>Author</option></select><?php if((int)$admin['id']!==$currentId):?><button class="account-danger" type="submit">Save role</button><?php endif;?></form></td><td><?=e(date('M j, Y',strtotime($admin['created_at'])))?></td><td><?php if((int)$admin['id']!==$currentId && count($admins)>1):?><form method="post" onsubmit="return confirm('Hapus akses admin ini? Tindakan ini tidak dapat dibatalkan.');"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete_admin"><input type="hidden" name="user_id" value="<?=(int)$admin['id']?>"><button class="account-danger" type="submit">Remove</button></form><?php else:?>—<?php endif;?></td></tr><?php endforeach;?></tbody></table></div><p class="account-note">Akun yang sedang kamu gunakan tidak dapat dihapus dari halaman ini. Akun admin terakhir juga dilindungi.</p></section><div style="display:grid;gap:18px"><section class="account-card"><h2>Change your password</h2><p>Use a unique password of at least 12 characters. Changing it updates your current account only.</p><form class="account-form" method="post" autocomplete="off"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="change_password"><label>Current password<input type="password" name="current_password" autocomplete="current-password" required maxlength="1024"></label><label>New password<input type="password" name="new_password" autocomplete="new-password" required minlength="12" maxlength="1024"></label><label>Confirm new password<input type="password" name="confirm_password" autocomplete="new-password" required minlength="12" maxlength="1024"></label><button class="button" type="submit">Update password ↗</button></form></section><section class="account-card"><h2>Add an administrator</h2><p>Create a separate login instead of sharing credentials. The new account will have the same admin privileges as yours.</p><form class="account-form" method="post" autocomplete="off"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="create_admin"><label>Full name<input name="name" required maxlength="120" autocomplete="name"></label><label>Email address<input type="email" name="email" required maxlength="190" autocomplete="email"></label><label>Role<select name="role" required><option value="author">Author — own stories</option><option value="editor">Editor — content team</option><option value="administrator">Administrator — full access</option></select></label><label>Temporary password<input type="password" name="password" required minlength="12" maxlength="1024" autocomplete="new-password"><small>At least 12 characters. Share it securely and ask the new account holder to change it after signing in.</small></label><button class="button" type="submit">Create account ↗</button></form></section></div></div></div></main><script>window.NOWRD_VISUAL = <?=json_encode(nowrd_visual_preferences(), JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>; window.NOWRD_VISUAL_ADMIN = true; window.NOWRD_CSRF = <?=json_encode(csrf_token())?>; window.NOWRD_VISUAL_SAVE_URL = <?=json_encode(base_url('admin/visual-settings.php'))?>;</script><script src="<?=e(base_url('public/assets/ui-v10.js'))?>" defer></script></body></html>
