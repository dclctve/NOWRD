<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
require_permission('media.manage');

// Upgrade existing v5 installs without requiring a reinstall or SQL import.
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS media (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, original_name VARCHAR(255) NOT NULL, file_name VARCHAR(100) NOT NULL UNIQUE, file_path VARCHAR(500) NOT NULL, mime_type VARCHAR(100) NOT NULL, file_size BIGINT UNSIGNED NOT NULL, width INT UNSIGNED NOT NULL, height INT UNSIGNED NOT NULL, alt_text VARCHAR(300) NOT NULL DEFAULT '', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_media_created (created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $ex) {
    error_log('NOWRD media schema check failed: '.$ex->getMessage());
    http_response_code(500); exit('Media Library belum dapat disiapkan. Periksa izin CREATE TABLE pada database.');
}
$picker = ($_GET['picker'] ?? '') === '1';
$error = ''; $success = '';
$uploadDir = dirname(__DIR__).'/uploads/media';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
    $error = 'Folder uploads/media tidak dapat dibuat. Periksa izin folder hosting.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'upload');
    if ($action === 'upload') {
        if (!isset($_FILES['media_file']) || !is_array($_FILES['media_file'])) {
            $error = 'Pilih gambar yang ingin diunggah.';
        } else {
            $file = $_FILES['media_file'];
            $errCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($errCode !== UPLOAD_ERR_OK) {
                $messages = [UPLOAD_ERR_INI_SIZE=>'Ukuran gambar melewati batas PHP.', UPLOAD_ERR_FORM_SIZE=>'Ukuran gambar terlalu besar.', UPLOAD_ERR_PARTIAL=>'Unggahan belum selesai.', UPLOAD_ERR_NO_FILE=>'Pilih gambar terlebih dahulu.', UPLOAD_ERR_NO_TMP_DIR=>'Folder sementara upload tidak tersedia.', UPLOAD_ERR_CANT_WRITE=>'Server tidak dapat menulis file.', UPLOAD_ERR_EXTENSION=>'Upload diblokir oleh ekstensi PHP.'];
                $error = $messages[$errCode] ?? 'Upload gagal. Coba lagi.';
            } elseif ((int)$file['size'] < 1 || (int)$file['size'] > 8 * 1024 * 1024) {
                $error = 'Ukuran gambar harus di bawah 8 MB.';
            } elseif (!is_uploaded_file((string)$file['tmp_name'])) {
                $error = 'Sumber upload tidak valid.';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = (string)$finfo->file((string)$file['tmp_name']);
                $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp','image/avif'=>'avif'];
                $info = @getimagesize((string)$file['tmp_name']);
                if (!isset($allowed[$mime]) || !$info || empty($info[0]) || empty($info[1])) {
                    $error = 'Format tidak didukung. Gunakan JPG, PNG, GIF, WebP, atau AVIF yang valid. SVG tidak diizinkan.';
                } elseif ((int)$info[0] > 12000 || (int)$info[1] > 12000 || ((int)$info[0] * (int)$info[1]) > 40000000) {
                    $error = 'Dimensi gambar terlalu besar (maksimal 12.000 px per sisi dan 40 megapiksel).';
                } else {
                    $randomName = bin2hex(random_bytes(20)).'.'.$allowed[$mime];
                    $target = $uploadDir.'/'.$randomName;
                    $relative = 'uploads/media/'.$randomName;
                    $alt = trim((string)($_POST['alt_text'] ?? ''));
                    $alt = text_substr($alt, 0, 300);
                    if (!move_uploaded_file((string)$file['tmp_name'], $target)) {
                        $error = 'Server tidak dapat menyimpan gambar. Periksa izin folder uploads/media.';
                    } else {
                        try {
                            $q = $pdo->prepare('INSERT INTO media(original_name,file_name,file_path,mime_type,file_size,width,height,alt_text) VALUES(?,?,?,?,?,?,?,?)');
                            $q->execute([text_substr(basename((string)$file['name']),0,255),$randomName,$relative,$mime,(int)$file['size'],(int)$info[0],(int)$info[1],$alt]);
                            nowrd_audit('media.uploaded','media',(int)$pdo->lastInsertId(),text_substr(basename((string)$file['name']),0,200));
                            header('Location: '.base_url('admin/media.php'.($picker?'?picker=1':'')).'&uploaded=1'); exit;
                        } catch (Throwable $ex) {
                            @unlink($target); error_log('NOWRD media insert failed: '.$ex->getMessage()); $error = 'Metadata gambar gagal disimpan ke database.';
                        }
                    }
                }
            }
        }
    } elseif ($action === 'delete') {
        $mediaId = (int)($_POST['media_id'] ?? 0);
        $q = $pdo->prepare('SELECT * FROM media WHERE id=?'); $q->execute([$mediaId]); $item = $q->fetch();
        if (!$item) $error = 'Gambar tidak ditemukan.';
        else {
            $url = base_url((string)$item['file_path']);
            $path = (string)$item['file_path'];
            $q = $pdo->prepare('SELECT COUNT(*) FROM posts WHERE cover_image=? OR content LIKE ?');
            $q->execute([$url, '%'.(string)$item['file_name'].'%']);
            if ((int)$q->fetchColumn() > 0) {
                $error = 'Gambar masih digunakan artikel. Ganti gambar di artikel terlebih dahulu sebelum menghapusnya.';
            } else {
                $disk = dirname(__DIR__).'/'.ltrim($path,'/');
                if (is_file($disk)) @unlink($disk);
                $pdo->prepare('DELETE FROM media WHERE id=?')->execute([$mediaId]);
                nowrd_audit('media.deleted','media',$mediaId,(string)$item['original_name']);
                header('Location: '.base_url('admin/media.php'.($picker?'?picker=1':'')).'&deleted=1'); exit;
            }
        }
    } elseif ($action === 'update_alt') {
        $mediaId = (int)($_POST['media_id'] ?? 0); $alt = text_substr(trim((string)($_POST['alt_text'] ?? '')),0,300);
        $q=$pdo->prepare('UPDATE media SET alt_text=? WHERE id=?'); $q->execute([$alt,$mediaId]);
        nowrd_audit('media.alt_updated','media',$mediaId,'Alternative text updated');
        header('Location: '.base_url('admin/media.php'.($picker?'?picker=1':'')).'&updated=1'); exit;
    }
}
if (isset($_GET['uploaded'])) $success='Gambar berhasil diunggah.';
if (isset($_GET['deleted'])) $success='Gambar berhasil dihapus.';
if (isset($_GET['updated'])) $success='Alt text diperbarui.';
$search = trim((string)($_GET['q'] ?? ''));
$kind = (string)($_GET['kind'] ?? 'all');
$sort = (string)($_GET['sort'] ?? 'newest');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 24;
$where = []; $params = [];
if ($search !== '') { $where[] = '(original_name LIKE ? OR alt_text LIKE ? OR file_name LIKE ?)'; $term = '%'.$search.'%'; array_push($params, $term, $term, $term); }
if ($kind === 'landscape') $where[] = 'width > height';
elseif ($kind === 'portrait') $where[] = 'height > width';
elseif ($kind === 'square') $where[] = 'width = height';
elseif ($kind === 'missing_alt') $where[] = "alt_text = ''";
$whereSql = $where ? ' WHERE '.implode(' AND ', $where) : '';
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM media'.$whereSql); $countStmt->execute($params); $totalItems = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalItems / $perPage)); $page = min($page, $totalPages); $offset = ($page - 1) * $perPage;
$orderSql = $sort === 'oldest' ? 'created_at ASC, id ASC' : ($sort === 'largest' ? 'file_size DESC, id DESC' : 'created_at DESC, id DESC');
$listStmt = $pdo->prepare('SELECT * FROM media'.$whereSql.' ORDER BY '.$orderSql.' LIMIT '.$perPage.' OFFSET '.$offset); $listStmt->execute($params); $items = $listStmt->fetchAll();
$baseQuery = ['picker'=>$picker ? '1' : null, 'q'=>$search !== '' ? $search : null, 'kind'=>$kind !== 'all' ? $kind : null, 'sort'=>$sort !== 'newest' ? $sort : null];
function media_page_url(array $base, int $page): string { $base['page'] = $page; return 'media.php?'.http_build_query(array_filter($base, static fn($v) => $v !== null && $v !== '')); }
function media_bytes(int $bytes): string { if($bytes >= 1048576) return number_format($bytes/1048576,1).' MB'; if($bytes >= 1024) return number_format($bytes/1024,0).' KB'; return $bytes.' B'; }
?><!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Media Library — NOWRD</title><link rel="stylesheet" href="<?=e(base_url('public/assets/style.css'))?>"><style>
.media-main{max-width:1180px;margin:0 auto;padding:clamp(22px,4vw,48px)}.media-head{display:flex;align-items:flex-end;justify-content:space-between;gap:18px;flex-wrap:wrap;margin-bottom:28px}.media-head h1{font-size:clamp(32px,5vw,48px);letter-spacing:-1.8px;margin:8px 0}.media-sub{color:var(--muted);margin:0}.upload-panel{border:1px solid var(--line);border-radius:18px;background:#fff;padding:20px;margin-bottom:26px}.upload-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(180px,.5fr) auto;gap:14px;align-items:end}.media-field label{display:block;font-size:12px;font-weight:750;margin-bottom:7px}.media-field input{width:100%;min-width:0;border:1px solid var(--line);border-radius:10px;padding:12px;font:inherit;background:#fff}.media-field small{display:block;color:var(--muted);margin-top:6px}.upload-btn{border:0;background:var(--ink);color:white;border-radius:10px;padding:13px 17px;font:inherit;font-weight:750;cursor:pointer}.media-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.media-card{border:1px solid var(--line);border-radius:16px;overflow:hidden;background:white;min-width:0}.media-thumb{height:210px;background:#f1f2ed;display:flex;align-items:center;justify-content:center;overflow:hidden}.media-thumb img{width:100%;height:100%;object-fit:cover}.media-details{padding:14px}.media-name{font-size:13px;font-weight:750;overflow-wrap:anywhere}.media-meta{color:var(--muted);font-size:11px;margin:5px 0 12px}.media-url{font-size:11px;overflow-wrap:anywhere;color:var(--muted);background:#f6f7f4;border-radius:8px;padding:8px}.media-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}.media-actions button,.media-actions a{border:1px solid var(--line);background:#fff;border-radius:8px;padding:8px 10px;font:inherit;font-size:12px;cursor:pointer;color:var(--ink);text-decoration:none}.media-actions .use-btn{background:var(--accent);border-color:var(--accent);font-weight:750}.alt-form{margin-top:10px}.alt-form input{width:100%;border:1px solid var(--line);border-radius:8px;padding:8px;font:inherit;font-size:12px}.alert{padding:12px 14px;background:#fff0ed;border-radius:10px;color:#a52d1b;margin:16px 0}.success{padding:12px 14px;background:#effbe3;border:1px solid #d5edbd;border-radius:10px;margin:16px 0}.media-filters{display:grid;grid-template-columns:minmax(180px,1fr) minmax(140px,.35fr) minmax(140px,.35fr) auto;gap:10px;align-items:end;margin:0 0 18px;padding:16px;border:1px solid var(--line);border-radius:14px;background:var(--surface,#fff)}.media-filters label{display:block;font-size:11px;font-weight:750;margin-bottom:6px}.media-filters input,.media-filters select{width:100%;min-width:0;border:1px solid var(--line);border-radius:9px;padding:10px;font:inherit;background:#fff;color:#222}.media-filters button,.media-filters a{display:inline-flex;align-items:center;justify-content:center;border:1px solid var(--line);border-radius:9px;padding:10px 13px;font:inherit;text-decoration:none;cursor:pointer;background:#fff;color:#222}.media-summary{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;color:var(--muted);font-size:12px;margin:0 0 14px}.media-pagination{display:flex;justify-content:center;gap:8px;align-items:center;margin:22px 0;flex-wrap:wrap}.media-pagination a,.media-pagination span{border:1px solid var(--line);border-radius:9px;padding:9px 12px;text-decoration:none;color:var(--ink);background:#fff;font-size:13px}.media-pagination .current{background:var(--ink);color:#fff}@media(max-width:700px){.media-filters{grid-template-columns:1fr 1fr}.media-filters .search-field{grid-column:1/-1}}.media-empty{text-align:center;padding:50px 18px;color:var(--muted);border:1px dashed var(--line);border-radius:16px}.picker-note{background:#f3f7ec;padding:12px 14px;border-radius:10px;margin-bottom:20px;font-size:13px}@media(max-width:850px){.media-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.upload-grid{grid-template-columns:1fr 1fr}.upload-btn{grid-column:1/-1}}@media(max-width:560px){.media-grid{grid-template-columns:1fr 1fr;gap:10px}.media-thumb{height:140px}.media-details{padding:10px}.upload-grid{grid-template-columns:1fr}.upload-btn{grid-column:auto}.media-main{padding:20px 13px}}
</style></head><body class="dashboard"><aside class="sidebar"><a class="brand" href="<?=e(base_url('admin/index.php'))?>"><span class="brand-mark"><?=e(setting('brand_mark','n.'))?></span> <?=e(setting('brand_name','NOWRD'))?></a><div class="side-label">WORKSPACE</div><a class="side-link" href="<?=e(base_url('admin/index.php'))?>">▦ &nbsp; Overview</a><a class="side-link" href="<?=e(base_url('admin/post.php'))?>">✎ &nbsp; New story</a><a class="side-link active" href="<?=e(base_url('admin/media.php'))?>">▧ &nbsp; Media Library</a><a class="side-link" href="<?=e(base_url('admin/settings.php'))?>">⚙ &nbsp; Settings</a><a class="side-link" href="<?=e(base_url('admin/users.php'))?>">♙ &nbsp; Admin accounts</a><a class="side-link" href="<?=e(base_url('admin/maintenance.php'))?>">⌁ &nbsp; Maintenance</a><a class="side-link" href="<?=e(base_url('index.php'))?>">↗ &nbsp; View website</a><div class="side-bottom"><div class="avatar"><?=e(strtoupper(substr($_SESSION['admin_name']??'N',0,1)))?></div><div><b><?=e($_SESSION['admin_name']??'Editor')?></b><small>Administrator</small></div><form method="post" action="<?=e(base_url('admin/index.php'))?>" class="logout-form"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><button class="logout" type="submit" name="logout" value="1" aria-label="Logout">↗</button></form></div></aside><main class="admin-main"><div class="media-main"><header class="media-head"><div><div class="eyebrow">YOUR WORKSPACE / ASSETS</div><h1>Media Library<span class="lime">.</span></h1><p class="media-sub">Unggah dan gunakan kembali gambar di artikel NOWRD.</p></div><?php if(!$picker): ?><a class="button" style="margin:0;width:auto" href="<?=e(base_url('admin/post.php'))?>">＋ New story</a><?php else: ?><button class="button" style="margin:0;width:auto" onclick="window.close()">Close picker</button><?php endif; ?></header>
<?php if($picker): ?><div class="picker-note">Pilih <b>Use in article</b> untuk memasukkan gambar ke editor yang membuka jendela ini. Jendela akan menutup otomatis.</div><?php endif; ?>
<?php if($error): ?><div class="alert" role="alert"><?=e($error)?></div><?php endif; ?><?php if($success): ?><div class="success" role="status"><?=e($success)?></div><?php endif; ?>
<section class="upload-panel"><form method="post" enctype="multipart/form-data"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="upload"><div class="upload-grid"><div class="media-field"><label for="mediaFile">Choose image</label><input id="mediaFile" type="file" name="media_file" accept="image/jpeg,image/png,image/gif,image/webp,image/avif" required><small>JPG, PNG, GIF, WebP, AVIF · Maks. 8 MB · SVG tidak diizinkan.</small></div><div class="media-field"><label for="altText">Alternative text (optional)</label><input id="altText" name="alt_text" maxlength="300" placeholder="Describe the image"></div><button class="upload-btn" type="submit">Upload image ↗</button></div></form></section>
<form class="media-filters" method="get"><input type="hidden" name="picker" value="<?= $picker?'1':'0' ?>"><div class="search-field"><label for="mediaSearch">Cari media</label><input id="mediaSearch" name="q" value="<?=e($search)?>" placeholder="Nama file, alt text..."></div><div><label for="mediaKind">Filter gambar</label><select id="mediaKind" name="kind"><option value="all" <?=$kind==='all'?'selected':''?>>Semua gambar</option><option value="landscape" <?=$kind==='landscape'?'selected':''?>>Landscape</option><option value="portrait" <?=$kind==='portrait'?'selected':''?>>Portrait</option><option value="square" <?=$kind==='square'?'selected':''?>>Square</option><option value="missing_alt" <?=$kind==='missing_alt'?'selected':''?>>Tanpa alt text</option></select></div><div><label for="mediaSort">Urutkan</label><select id="mediaSort" name="sort"><option value="newest" <?=$sort==='newest'?'selected':''?>>Terbaru</option><option value="oldest" <?=$sort==='oldest'?'selected':''?>>Terlama</option><option value="largest" <?=$sort==='largest'?'selected':''?>>Ukuran terbesar</option></select></div><div style="display:flex;gap:7px"><button type="submit">Terapkan</button><a href="<?=e(base_url('admin/media.php'.($picker?'?picker=1':'')))?>">Reset</a></div></form><div class="media-summary"><span><?=number_format($totalItems)?> media ditemukan · halaman <?= $page ?> dari <?= $totalPages ?></span><span>Menampilkan maks. <?= $perPage ?> item per halaman</span></div>
<?php if(!$items): ?><div class="media-empty"><h2><?= $totalItems ? 'Tidak ada hasil pada halaman ini' : 'Belum ada media yang cocok' ?></h2><p>Coba ubah kata kunci atau filter, atau unggah gambar baru.</p></div><?php else: ?><div class="media-grid"><?php foreach($items as $item): $url=base_url((string)$item['file_path']); ?><article class="media-card"><div class="media-thumb"><img src="<?=e($url)?>" alt="<?=e($item['alt_text'])?>" loading="lazy"></div><div class="media-details"><div class="media-name"><?=e($item['original_name'])?></div><div class="media-meta"><?=e(strtoupper(pathinfo($item['file_name'],PATHINFO_EXTENSION)))?> · <?=e(media_bytes((int)$item['file_size']))?> · <?= (int)$item['width']?>×<?= (int)$item['height']?></div><div class="media-url" data-url="<?=e($url)?>"><?=e($url)?></div><div class="media-actions"><?php if($picker): ?><button class="use-btn" type="button" data-use-url="<?=e($url)?>" data-use-alt="<?=e($item['alt_text'])?>">Use in article ↗</button><?php else: ?><button type="button" data-copy-url="<?=e($url)?>">Copy URL</button><?php endif; ?><form method="post" onsubmit="return confirm('Hapus gambar ini secara permanen?')"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="media_id" value="<?= (int)$item['id']?>"><button type="submit">Delete</button></form></div><form class="alt-form" method="post"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="update_alt"><input type="hidden" name="media_id" value="<?= (int)$item['id']?>"><label class="sr-only" for="alt-<?= (int)$item['id']?>">Alternative text</label><input id="alt-<?= (int)$item['id']?>" name="alt_text" maxlength="300" value="<?=e($item['alt_text'])?>" placeholder="Alternative text"><button type="submit" style="margin-top:7px">Save alt text</button></form></div></article><?php endforeach; ?></div><?php if($totalPages>1): ?><nav class="media-pagination" aria-label="Pagination media"><?php if($page>1): ?><a href="<?=e(base_url(media_page_url($baseQuery,$page-1)))?>">← Sebelumnya</a><?php endif; ?><span class="current"><?= $page ?> / <?= $totalPages ?></span><?php if($page<$totalPages): ?><a href="<?=e(base_url(media_page_url($baseQuery,$page+1)))?>">Berikutnya →</a><?php endif; ?></nav><?php endif; ?><?php endif; ?></div></main>
<script>
(()=>{'use strict';const picker=<?= $picker?'true':'false' ?>;document.querySelectorAll('[data-copy-url]').forEach(b=>b.addEventListener('click',async()=>{const url=b.closest('.media-card').querySelector('[data-url]').dataset.url;try{await navigator.clipboard.writeText(url);b.textContent='Copied!';}catch(e){window.prompt('Copy image URL',url);}}));document.querySelectorAll('[data-use-url]').forEach(b=>b.addEventListener('click',()=>{if(!window.opener||window.opener.closed){alert('Editor asal tidak ditemukan. Buka Media Library dari tombol di editor.');return;}window.opener.postMessage({type:'nowrd:select-media',url:b.dataset.useUrl,alt:b.dataset.useAlt||''},window.location.origin);window.close();}));})();
</script><script>window.NOWRD_VISUAL = <?=json_encode(nowrd_visual_preferences(), JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>; window.NOWRD_VISUAL_ADMIN = true; window.NOWRD_CSRF = <?=json_encode(csrf_token())?>; window.NOWRD_VISUAL_SAVE_URL = <?=json_encode(base_url('admin/visual-settings.php'))?>;</script><script src="<?=e(base_url('public/assets/ui-v10.js'))?>" defer></script>
</body></html>
