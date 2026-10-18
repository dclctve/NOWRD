<?php
declare(strict_types=1);

$configFile = dirname(__DIR__) . '/config.php';
if (!is_file($configFile)) {
    http_response_code(503);
    exit('NOWRD belum dikonfigurasi. Jalankan wizard instalasi terlebih dahulu.');
}
$config = require $configFile;
if (!is_array($config)) {
    http_response_code(500);
    exit('Konfigurasi NOWRD tidak valid. Periksa config.php.');
}

date_default_timezone_set($config['timezone'] ?? 'Asia/Jakarta');
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('NOWRDSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
    // v15: expire authenticated admin sessions after 30 minutes of inactivity.
    if (isset($_SESSION['admin_id'])) {
        $lastActivity = (int)($_SESSION['_last_activity'] ?? time());
        if ($lastActivity > 0 && (time() - $lastActivity) > 1800) {
            unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['_last_activity']);
            $_SESSION['auth_notice'] = 'Sesi admin berakhir karena tidak aktif selama 30 menit. Silakan login kembali.';
            session_regenerate_id(true);
        } else {
            $_SESSION['_last_activity'] = time();
        }
    }
}
header('X-Content-Type-Options: nosniff');
header('X-Permitted-Cross-Domain-Policies: none');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

try {
    $dsn = 'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (Throwable $exception) {
    error_log('NOWRD database connection failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('NOWRD sementara tidak dapat terhubung ke database. Periksa konfigurasi atau hubungi administrator.');
}

function text_length(string $value): int { return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value); }
function text_substr(string $value, int $start, int $length): string { return function_exists('mb_substr') ? mb_substr($value, $start, $length, 'UTF-8') : substr($value, $start, $length); }

function e(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_url(string $path = ''): string {
    global $config;
    $base = rtrim((string)($config['base_url'] ?? ''), '/');
    if ($base === '') {
        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/'));
        // Derive the installation root, not the current script directory (admin pages are nested).
        $script = preg_replace('~/(?:admin/[^/]+|install\.php|article\.php|index\.php)$~', '', $script) ?? $script;
        $base = rtrim($script, '/');
    }
    return $base . '/' . ltrim($path, '/');
}

function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return (string)$_SESSION['_csrf'];
}

function verify_csrf(): void {
    $known = (string)($_SESSION['_csrf'] ?? '');
    $sent = (string)($_POST['_csrf'] ?? '');
    if ($known === '' || $sent === '' || !hash_equals($known, $sent)) {
        http_response_code(419);
        exit('Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.');
    }
}

function is_admin(): bool { return isset($_SESSION['admin_id']) && (int)$_SESSION['admin_id'] > 0; }
function require_admin(): void {
    if (!is_admin()) {
        header('Location: ' . base_url('admin/login.php'));
        exit;
    }
}

/** Additive, idempotent role migration. Existing users retain administrator access. */
function ensure_role_schema(): void {
    static $ready = false;
    if ($ready) return;
    global $pdo;
    $column = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
    if (!$column) {
        $pdo->exec("ALTER TABLE users ADD COLUMN role ENUM('administrator','editor','author') NOT NULL DEFAULT 'administrator'");
    }
    $pdo->exec("UPDATE users SET role='administrator' WHERE role IS NULL OR role=''");
    $ready = true;
}

function current_admin_role(): string {
    if (!is_admin()) return 'guest';
    ensure_role_schema();
    global $pdo;
    $q = $pdo->prepare('SELECT role FROM users WHERE id=? LIMIT 1');
    $q->execute([(int)$_SESSION['admin_id']]);
    $role = (string)($q->fetchColumn() ?: '');
    if (!in_array($role, ['administrator','editor','author'], true)) {
        unset($_SESSION['admin_id'], $_SESSION['admin_name']);
        session_regenerate_id(true);
        header('Location: ' . base_url('admin/login.php'));
        exit;
    }
    $_SESSION['admin_role'] = $role;
    return $role;
}

function can(string $capability): bool {
    $role = current_admin_role();
    $permissions = [
        'administrator' => ['content.view','content.create','content.edit.any','content.delete.any','media.manage','taxonomy.manage','settings.manage','users.manage','maintenance.manage'],
        'editor' => ['content.view','content.create','content.edit.any','content.delete.any','media.manage','taxonomy.manage'],
        'author' => ['content.view','content.create','content.edit.own','content.delete.own'],
    ];
    return in_array($capability, $permissions[$role] ?? [], true);
}

function require_permission(string $capability): void {
    require_admin();
    if (!can($capability)) {
        http_response_code(403);
        echo '<!doctype html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Akses ditolak — NOWRD</title><body style="font:16px system-ui;max-width:620px;margin:12vh auto;padding:24px;color:#222"><p style="letter-spacing:.12em;font-size:12px">NOWRD / ACCESS CONTROL</p><h1>Akses tidak diizinkan.</h1><p>Akun kamu tidak memiliki izin untuk membuka halaman ini. Hubungi administrator jika akses ini diperlukan.</p><p><a href="'.e(base_url('admin/index.php')).'">Kembali ke dashboard</a></p></body></html>';
        exit;
    }
}

function require_post_permission(array $post, string $operation = 'edit'): void {
    $role = current_admin_role();
    if ($role === 'administrator' || $role === 'editor') {
        $cap = $operation === 'delete' ? 'content.delete.any' : 'content.edit.any';
        require_permission($cap);
        return;
    }
    if ($role !== 'author' || (int)($post['author_id'] ?? 0) !== (int)($_SESSION['admin_id'] ?? 0)) {
        http_response_code(403);
        exit('Kamu hanya dapat mengelola artikel milik akun sendiri.');
    }
    require_permission($operation === 'delete' ? 'content.delete.own' : 'content.edit.own');
}

function slugify(string $value): string {
    $converted = function_exists('iconv') ? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) : false;
    $value = strtolower(trim($converted !== false ? $converted : $value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-') ?: 'artikel';
}

/** File-backed cache designed for shared hosting without APCu/Redis. Cache data lives outside the web root when possible. */
function nowrd_cache_dir(): string {
    static $dir = null;
    if ($dir !== null) return $dir;
    $base = rtrim((string)sys_get_temp_dir(), DIRECTORY_SEPARATOR);
    $candidate = $base . DIRECTORY_SEPARATOR . 'nowrd-cache-' . substr(hash('sha256', dirname(__DIR__)), 0, 16);
    if (!is_dir($candidate) && !@mkdir($candidate, 0700, true) && !is_dir($candidate)) {
        $candidate = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache';
        if (!is_dir($candidate)) @mkdir($candidate, 0700, true);
        if (is_dir($candidate) && !is_file($candidate . DIRECTORY_SEPARATOR . '.htaccess')) @file_put_contents($candidate . DIRECTORY_SEPARATOR . '.htaccess', "Require all denied\nDeny from all\n");
        if (is_dir($candidate) && !is_file($candidate . DIRECTORY_SEPARATOR . 'index.html')) @file_put_contents($candidate . DIRECTORY_SEPARATOR . 'index.html', '');
    }
    $dir = is_dir($candidate) && is_writable($candidate) ? $candidate : '';
    return $dir;
}
function nowrd_cache_version(): string {
    $dir = nowrd_cache_dir(); if ($dir === '') return 'disabled';
    $file = $dir . DIRECTORY_SEPARATOR . '.version';
    if (!is_file($file)) @file_put_contents($file, '1', LOCK_EX);
    return trim((string)@file_get_contents($file)) ?: '1';
}
function nowrd_cache_stat(string $event): void {
    $dir = nowrd_cache_dir(); if ($dir === '' || !in_array($event, ['hit','miss','write'], true)) return;
    $path = $dir . DIRECTORY_SEPARATOR . '.stats.json';
    $fp = @fopen($path, 'c+'); if (!$fp) return;
    try { if (!flock($fp, LOCK_EX)) return; $raw = stream_get_contents($fp); $stats = json_decode($raw ?: '{}', true); if (!is_array($stats)) $stats = []; $stats[$event] = max(0, (int)($stats[$event] ?? 0)) + 1; $stats['updated_at'] = gmdate('c'); rewind($fp); ftruncate($fp, 0); fwrite($fp, json_encode($stats)); fflush($fp); flock($fp, LOCK_UN); } finally { fclose($fp); }
}
function nowrd_cache_stats(): array {
    $dir = nowrd_cache_dir(); $path = $dir !== '' ? $dir . DIRECTORY_SEPARATOR . '.stats.json' : '';
    $stats = $path !== '' && is_file($path) ? json_decode((string)@file_get_contents($path), true) : [];
    return ['enabled'=>$dir !== '', 'directory'=>$dir, 'hits'=>(int)($stats['hit'] ?? 0), 'misses'=>(int)($stats['miss'] ?? 0), 'writes'=>(int)($stats['write'] ?? 0), 'updated_at'=>(string)($stats['updated_at'] ?? '')];
}
function nowrd_cache_ttl(string $settingKey, int $default, int $min = 15, int $max = 3600): int {
    $value = (int)setting($settingKey, (string)$default); return max($min, min($max, $value));
}
function cache_remember(string $key, int $ttl, callable $producer): mixed {
    $dir = nowrd_cache_dir();
    if ($dir === '' || $ttl < 1) return $producer();
    $file = $dir . DIRECTORY_SEPARATOR . hash('sha256', nowrd_cache_version() . '|' . $key) . '.cache';
    if (is_file($file) && (time() - (int)@filemtime($file)) < $ttl) {
        $raw = @file_get_contents($file);
        if ($raw !== false) { $value = @unserialize($raw, ['allowed_classes' => false]); if ($value !== false || $raw === serialize(false)) { nowrd_cache_stat('hit'); return $value; } }
    }
    nowrd_cache_stat('miss'); $value = $producer();
    $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, serialize($value), LOCK_EX) !== false && @rename($tmp, $file)) nowrd_cache_stat('write');
    else @unlink($tmp);
    return $value;
}
function cache_clear(): void {
    $dir = nowrd_cache_dir(); if ($dir === '') return;
    $file = $dir . DIRECTORY_SEPARATOR . '.version';
    @file_put_contents($file, bin2hex(random_bytes(12)), LOCK_EX);
}
function nowrd_visual_preferences(): array {
    $defaults = ['mode'=>'classic','theme'=>'light','palette'=>'classic','motion'=>'smooth','blur'=>24,'alpha'=>68,'dockSize'=>'regular','dockPosition'=>'center'];
    $raw = setting('nowrd_visual_preferences', '');
    $saved = json_decode($raw, true);
    if (!is_array($saved)) $saved = [];
    $prefs = array_merge($defaults, array_intersect_key($saved, $defaults));
    if (!in_array($prefs['mode'], ['classic','modern'], true)) $prefs['mode']='classic';
    if (!in_array($prefs['theme'], ['light','dark','system'], true)) $prefs['theme']='light';
    if (!in_array($prefs['palette'], ['classic','glass','ocean','violet','forest','sunset'], true)) $prefs['palette']='classic';
    if (!in_array($prefs['motion'], ['off','subtle','smooth','playful'], true)) $prefs['motion']='smooth';
    if (!in_array($prefs['dockSize'], ['compact','regular','large'], true)) $prefs['dockSize']='regular';
    if (!in_array($prefs['dockPosition'], ['center','left','right','left-center','right-center'], true)) $prefs['dockPosition']='center';
    $prefs['blur']=max(0,min(40,(int)$prefs['blur'])); $prefs['alpha']=max(20,min(96,(int)$prefs['alpha']));
    return $prefs;
}

function setting(string $key, string $default = ''): string {
    static $memo = [];
    global $pdo;
    if (array_key_exists($key, $memo)) return $memo[$key];
    try {
        $query = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $query->execute([$key]);
        $value = $query->fetchColumn();
        return $memo[$key] = ($value === false ? $default : (string)$value);
    } catch (Throwable $exception) {
        return $memo[$key] = $default;
    }
}

/** Additive, idempotent editorial workflow migration. Public queries expose only published stories. */
function ensure_workflow_schema(): void {
    static $ready = false; if ($ready) return; global $pdo;
    try {
        $pdo->exec("ALTER TABLE posts MODIFY status ENUM('draft','in_review','approved','published') NOT NULL DEFAULT 'draft'");
        $pdo->exec("ALTER TABLE post_revisions MODIFY status ENUM('draft','in_review','approved','published') NOT NULL DEFAULT 'draft'");
    } catch (Throwable $e) {
        // Older installations may not have revisions yet; the next schema migration will create it.
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS post_workflow_events (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, post_id INT UNSIGNED NOT NULL, actor_id INT UNSIGNED NULL, from_status VARCHAR(24) NOT NULL DEFAULT '', to_status VARCHAR(24) NOT NULL, note VARCHAR(500) NOT NULL DEFAULT '', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_workflow_post(post_id,created_at), CONSTRAINT fk_workflow_post FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE CASCADE, CONSTRAINT fk_workflow_actor FOREIGN KEY(actor_id) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $ready = true;
}

/**
 * Sanitize editor HTML with a strict allow-list. No arbitrary styles, classes,
 * event handlers, embedded media, or scriptable URL schemes are retained.
 * Works without DOMDocument so it remains compatible with common shared hosting.
 */
function sanitize_rich_html(string $html): string {
    if (strlen($html) > 1_000_000) {
        $html = substr($html, 0, 1_000_000);
    }
    // Remove active elements together with their contents before allowing tags.
    $html = preg_replace('~<(script|style|iframe|object|embed|svg|math|form|input|button|textarea|select|video|audio|source|link|meta|base|template|noscript)\b[^>]*>.*?</\1\s*>~is', '', $html) ?? '';
    $html = preg_replace('~<(script|style|iframe|object|embed|svg|math|form|input|button|textarea|select|video|audio|source|link|meta|base|template|noscript)\b[^>]*/?>~is', '', $html) ?? '';
    $allowed = '<p><br><strong><b><em><i><u><s><del><h2><h3><h4><blockquote><ul><ol><li><a><img><hr><pre><code>'; 
    $html = strip_tags($html, $allowed);

    return preg_replace_callback('~<(\/)?([a-z0-9]+)\b([^>]*)>~i', static function (array $match): string {
        $closing = ($match[1] ?? '') === '/';
        $tag = strtolower($match[2] ?? '');
        $allowedTags = ['p','br','strong','b','em','i','u','s','del','h2','h3','h4','blockquote','ul','ol','li','a','img','hr','pre','code'];
        if (!in_array($tag, $allowedTags, true)) return '';
        if ($closing) return in_array($tag, ['br','img','hr'], true) ? '' : '</'.$tag.'>';
        if (in_array($tag, ['br','hr'], true)) return '<'.$tag.'>';
        $attrs = [];
        if ($tag === 'a' || $tag === 'img') {
            preg_match_all('~\b(href|src|alt|title)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'`=<>]+))~i', $match[3] ?? '', $found, PREG_SET_ORDER);
            foreach ($found as $attr) {
                $name = strtolower($attr[1]);
                if ($name === 'href' && $tag !== 'a') continue;
                if ($name === 'src' && $tag !== 'img') continue;
                $raw = $attr[2] !== '' ? $attr[2] : ($attr[3] !== '' ? $attr[3] : ($attr[4] ?? ''));
                $value = trim(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($name === 'href') {
                    $safe = valid_http_url($value) || preg_match('~^(#|/(?!/)|\?(?!/))~', $value) === 1 || preg_match('~^mailto:[^\s]+$~i', $value) === 1;
                    if (!$safe) continue;
                } elseif ($name === 'src') {
                    if (!valid_http_url($value) && preg_match('~^/?uploads/[a-zA-Z0-9._/-]+$~', $value) !== 1) continue;
                } elseif ($name === 'alt' || $name === 'title') {
                    $value = text_substr($value, 0, 300);
                }
                $attrs[$name] = $value;
            }
        }
        if ($tag === 'img' && empty($attrs['src'])) return '';
        $out = '<'.$tag;
        foreach ($attrs as $name => $value) $out .= ' '.$name.'="'.e($value).'"';
        return $out.'>';
    }, $html) ?? '';
}

function render_rich_html(string $html): string {
    // v4 stored plain text. Escape it and preserve its line breaks; only treat
    // content as rich HTML when it contains one of this editor's allowed tags.
    if (preg_match('~</?(?:p|br|strong|b|em|i|u|s|del|h2|h3|h4|blockquote|ul|ol|li|a|img|hr|pre|code)\b~i', $html) !== 1) {
        return nl2br(e($html), false);
    }
    return sanitize_rich_html($html);
}

function rich_html_to_text(string $html): string {
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    return trim($text);
}

function valid_http_url(string $url): bool {
    if ($url === '') return true;
    if (strlen($url) > 500 || !filter_var($url, FILTER_VALIDATE_URL)) return false;
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true);
}


/** Idempotent taxonomy migration for existing v5/v6 installations. */
function ensure_taxonomy_schema(): void {
    static $ready = false;
    if ($ready) return;
    global $pdo;
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, slug VARCHAR(140) NOT NULL UNIQUE, description VARCHAR(500) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS tags (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80) NOT NULL, slug VARCHAR(100) NOT NULL UNIQUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS post_categories (post_id INT UNSIGNED NOT NULL, category_id INT UNSIGNED NOT NULL, PRIMARY KEY(post_id,category_id), INDEX idx_pc_category(category_id), CONSTRAINT fk_pc_post FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE CASCADE, CONSTRAINT fk_pc_category FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS post_tags (post_id INT UNSIGNED NOT NULL, tag_id INT UNSIGNED NOT NULL, PRIMARY KEY(post_id,tag_id), INDEX idx_pt_tag(tag_id), CONSTRAINT fk_pt_post FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE CASCADE, CONSTRAINT fk_pt_tag FOREIGN KEY(tag_id) REFERENCES tags(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $ready = true;
}
/** Safe, repeatable schema upgrade for v8. Existing article rows are preserved. */
function ensure_v8_schema(): void {
    static $ready = false;
    if ($ready) return;
    global $pdo;
    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM posts')->fetchAll() as $column) $columns[$column['Field']] = true;
    $needed = [
        'seo_title' => "ALTER TABLE posts ADD COLUMN seo_title VARCHAR(220) NULL AFTER excerpt",
        'seo_description' => "ALTER TABLE posts ADD COLUMN seo_description VARCHAR(320) NULL AFTER seo_title",
        'canonical_url' => "ALTER TABLE posts ADD COLUMN canonical_url VARCHAR(500) NULL AFTER seo_description",
    ];
    foreach ($needed as $name => $sql) if (!isset($columns[$name])) $pdo->exec($sql);
    $pdo->exec("INSERT IGNORE INTO settings(setting_key,setting_value) VALUES ('site_description',''),('site_url','')");
    $ready = true;
}

/** Safe, repeatable schema upgrade for v9 revision history. */
function ensure_v9_schema(): void {
    static $ready = false;
    if ($ready) return;
    global $pdo;
    $pdo->exec("CREATE TABLE IF NOT EXISTS post_revisions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        post_id INT UNSIGNED NOT NULL,
        title VARCHAR(220) NOT NULL,
        slug VARCHAR(240) NOT NULL,
        excerpt TEXT NULL,
        seo_title VARCHAR(220) NULL,
        seo_description VARCHAR(320) NULL,
        canonical_url VARCHAR(500) NULL,
        content LONGTEXT NOT NULL,
        cover_image VARCHAR(500) NULL,
        status ENUM('draft','published') NOT NULL DEFAULT 'draft',
        published_at DATETIME NULL,
        created_by INT UNSIGNED NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_revisions_post_created (post_id, created_at),
        CONSTRAINT fk_revisions_post FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE CASCADE,
        CONSTRAINT fk_revisions_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $ready = true;
}



/** Privacy-conscious public analytics: aggregate daily counts only; no raw IP, cookies, or user-agent stored. */
function nowrd_track_page(string $type = 'page', ?int $postId = null): void {
    global $pdo;
    static $ready = false;
    try {
        if (!$ready) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS analytics_daily (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, day DATE NOT NULL, page_type VARCHAR(20) NOT NULL DEFAULT 'page', post_id INT UNSIGNED NULL, views INT UNSIGNED NOT NULL DEFAULT 0, UNIQUE KEY uq_analytics_day_type_post(day,page_type,post_id), INDEX idx_analytics_day(day), INDEX idx_analytics_post(post_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $ready = true;
        }
        if (is_admin() || (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET')) return;
        $type = in_array($type, ['page','article'], true) ? $type : 'page';
        $pdo->prepare("INSERT INTO analytics_daily(day,page_type,post_id,views) VALUES(CURDATE(),?,?,1) ON DUPLICATE KEY UPDATE views=views+1")->execute([$type, $type === 'article' && $postId ? $postId : null]);
    } catch (Throwable $e) { error_log('NOWRD analytics unavailable: '.$e->getMessage()); }
}

/** Small audit trail for consequential admin actions. Never stores secrets or article bodies. */
function nowrd_audit(string $action, string $entityType = 'system', ?int $entityId = null, string $summary = ''): void {
    global $pdo;
    static $ready = false;
    try {
        if (!$ready) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS admin_activity (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, admin_id INT UNSIGNED NULL, admin_name VARCHAR(120) NOT NULL DEFAULT '', action VARCHAR(80) NOT NULL, entity_type VARCHAR(50) NOT NULL DEFAULT 'system', entity_id BIGINT NULL, summary VARCHAR(240) NOT NULL DEFAULT '', ip_hash CHAR(64) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_activity_created(created_at), INDEX idx_activity_entity(entity_type,entity_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $ready = true;
        }
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $pdo->prepare('INSERT INTO admin_activity(admin_id,admin_name,action,entity_type,entity_id,summary,ip_hash) VALUES(?,?,?,?,?,?,?)')->execute([
            isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null,
            text_substr((string)($_SESSION['admin_name'] ?? 'Administrator'),0,120),
            text_substr($action,0,80), text_substr($entityType,0,50), $entityId,
            text_substr($summary,0,240), $ip !== '' ? hash('sha256',$ip) : null
        ]);
    } catch (Throwable $e) { error_log('NOWRD audit write failed: '.$e->getMessage()); }
}

function post_categories(int $postId): array {
    global $pdo; ensure_taxonomy_schema();
    $q=$pdo->prepare('SELECT c.id,c.name,c.slug FROM categories c JOIN post_categories pc ON pc.category_id=c.id WHERE pc.post_id=? ORDER BY c.name'); $q->execute([$postId]); return $q->fetchAll();
}
function post_tags(int $postId): array {
    global $pdo; ensure_taxonomy_schema();
    $q=$pdo->prepare('SELECT t.id,t.name,t.slug FROM tags t JOIN post_tags pt ON pt.tag_id=t.id WHERE pt.post_id=? ORDER BY t.name'); $q->execute([$postId]); return $q->fetchAll();
}
