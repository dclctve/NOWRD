<?php
require dirname(__DIR__).'/app/bootstrap.php';
require_permission('content.view');
ensure_taxonomy_schema();
ensure_v8_schema();
ensure_v9_schema();
ensure_workflow_schema();
$id = (int)($_GET['id'] ?? 0);
$allCategories = $pdo->query('SELECT id,name FROM categories ORDER BY name')->fetchAll();
$allTags = $pdo->query('SELECT id,name FROM tags ORDER BY name')->fetchAll();
$selectedCategoryIds = []; $selectedTagNames = '';

$post = ['title'=>'','slug'=>'','excerpt'=>'','seo_title'=>'','seo_description'=>'','canonical_url'=>'','content'=>'','cover_image'=>'','status'=>'draft','published_at'=>null];
if ($id) {
    $q = $pdo->prepare('SELECT * FROM posts WHERE id=?');
    $q->execute([$id]);
    $found = $q->fetch();
    if (!$found) { http_response_code(404); exit('Artikel tidak ditemukan'); }
    if (current_admin_role() === 'author') require_post_permission($found, 'edit');
    $post = $found;
    $selectedCategoryIds = array_map(static fn($r)=>(int)$r['id'], post_categories($id));
    $selectedTagNames = implode(', ', array_column(post_tags($id), 'name'));
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_revision'])) {
    verify_csrf();
    $revisionId = (int)$_POST['restore_revision'];
    if (!$id || $revisionId < 1) { http_response_code(400); exit('Permintaan pemulihan tidak valid.'); }
    try {
        $pdo->beginTransaction();
        $currentQ = $pdo->prepare('SELECT * FROM posts WHERE id=? FOR UPDATE');
        $currentQ->execute([$id]);
        $current = $currentQ->fetch();
        $revisionQ = $pdo->prepare('SELECT * FROM post_revisions WHERE id=? AND post_id=?');
        $revisionQ->execute([$revisionId, $id]);
        $revision = $revisionQ->fetch();
        if (!$current || !$revision) throw new RuntimeException('Versi artikel tidak ditemukan.');
        $snapshot = $pdo->prepare('INSERT INTO post_revisions(post_id,title,slug,excerpt,seo_title,seo_description,canonical_url,content,cover_image,status,published_at,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
        $snapshot->execute([$id,$current['title'],$current['slug'],$current['excerpt'],$current['seo_title'] ?? null,$current['seo_description'] ?? null,$current['canonical_url'] ?? null,$current['content'],$current['cover_image'],$current['status'],$current['published_at'],$_SESSION['admin_id']]);
        $restore = $pdo->prepare('UPDATE posts SET title=?,slug=?,excerpt=?,seo_title=?,seo_description=?,canonical_url=?,content=?,cover_image=?,status=?,published_at=? WHERE id=?');
        $restore->execute([$revision['title'],$revision['slug'],$revision['excerpt'],$revision['seo_title'],$revision['seo_description'],$revision['canonical_url'],$revision['content'],$revision['cover_image'],$revision['status'],$revision['published_at'],$id]);
        $pdo->commit(); cache_clear();
        nowrd_audit('article.revision_restored','article',$id,(string)$revision['title']);
        header('Location: '.base_url('admin/post.php?id='.$id.'&restored=1'));
        exit;
    } catch (Throwable $restoreError) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('NOWRD revision restore failed: '.$restoreError->getMessage());
        $error = 'Versi artikel tidak dapat dipulihkan. Periksa log server jika masalah berlanjut.';
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete']) && !isset($_POST['restore_revision'])) {
    verify_csrf();
    $title = trim((string)($_POST['title'] ?? ''));
    $slug = slugify(trim((string)($_POST['slug'] ?? '')) ?: $title);
    $excerpt = trim((string)($_POST['excerpt'] ?? ''));
    $seoTitle = trim((string)($_POST['seo_title'] ?? ''));
    $seoDescription = trim((string)($_POST['seo_description'] ?? ''));
    $canonicalUrl = trim((string)($_POST['canonical_url'] ?? ''));
    $publishInput = trim((string)($_POST['published_at'] ?? ''));
    $publishAt = null;
    $requestedStatus = (string)($_POST['status'] ?? 'draft');
    $allowedStatuses = ['draft','in_review','approved','published'];
    $status = in_array($requestedStatus, $allowedStatuses, true) ? $requestedStatus : 'draft';
    $role = current_admin_role();
    if ($role === 'author' && in_array($status, ['approved','published'], true)) $status = 'in_review';
    if ($role === 'editor' && $status === 'approved') $status = 'approved';
    if ($status === 'published') {
        if ($publishInput !== '') { $dt = DateTime::createFromFormat('!Y-m-d\TH:i', $publishInput); $dateErrors = DateTime::getLastErrors(); if ($dt && $dt->format('Y-m-d\TH:i') === $publishInput && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0))) $publishAt = $dt->format('Y-m-d H:i:s'); else $error = 'Waktu publikasi tidak valid.'; }
        else $publishAt = date('Y-m-d H:i:s');
    }
    $rawContent = (string)($_POST['content'] ?? '');
    $content = sanitize_rich_html($rawContent);
    $cover = trim((string)($_POST['cover_image'] ?? ''));
    $categoryIds = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['category_ids'] ?? [])), static fn($v)=>$v>0)));
    $tagInput = trim((string)($_POST['tags'] ?? ''));
    $tagNames = array_values(array_unique(array_filter(array_map(static fn($v)=>trim($v), preg_split('/[,;]+/', $tagInput) ?: []))));
    $tagNames = array_slice(array_filter($tagNames, static fn($v)=>text_length($v)<=80), 0, 20);
    $selectedCategoryIds = $categoryIds; $selectedTagNames = implode(', ', $tagNames);
    if ($title === '' || rich_html_to_text($content) === '') {
        $error = 'Judul dan isi artikel wajib diisi.';
    } elseif (text_length($title) > 220) {
        $error = 'Judul maksimal 220 karakter.';
    } elseif ($slug === '' || text_length($slug) > 240) {
        $error = 'Slug maksimal 240 karakter.';
    } elseif (text_length($excerpt) > 60000) {
        $error = 'Deskripsi terlalu panjang.';
    } elseif (strlen($rawContent) > 1_000_000) {
        $error = 'Isi artikel maksimal 1 MB.';
    } elseif (!valid_http_url($cover)) {
        $error = 'URL gambar harus berupa URL HTTP atau HTTPS yang valid.';
    } elseif (text_length($seoTitle) > 220 || text_length($seoDescription) > 320) {
        $error = 'SEO title maksimal 220 karakter dan deskripsi SEO maksimal 320 karakter.';
    } elseif (!valid_http_url($canonicalUrl) || $canonicalUrl === '') {
        if ($canonicalUrl !== '') $error = 'Canonical URL harus berupa URL HTTP atau HTTPS yang valid.';
    }
    if ($error === '') {
        try {
            $pdo->beginTransaction();
            if ($id) {
                $oldQ = $pdo->prepare('SELECT * FROM posts WHERE id=? FOR UPDATE');
                $oldQ->execute([$id]);
                $oldPost = $oldQ->fetch();
                if ($oldPost) {
                    $revision = $pdo->prepare('INSERT INTO post_revisions(post_id,title,slug,excerpt,seo_title,seo_description,canonical_url,content,cover_image,status,published_at,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
                    $revision->execute([$id,$oldPost['title'],$oldPost['slug'],$oldPost['excerpt'],$oldPost['seo_title'] ?? null,$oldPost['seo_description'] ?? null,$oldPost['canonical_url'] ?? null,$oldPost['content'],$oldPost['cover_image'],$oldPost['status'],$oldPost['published_at'],$_SESSION['admin_id']]);
                }
                $q = $pdo->prepare('UPDATE posts SET title=?,slug=?,excerpt=?,seo_title=?,seo_description=?,canonical_url=?,content=?,cover_image=?,status=?,published_at=? WHERE id=?');
                $q->execute([$title,$slug,$excerpt,$seoTitle ?: null,$seoDescription ?: null,$canonicalUrl ?: null,$content,$cover ?: null,$status,$publishAt,$id]);
            } else {
                $q = $pdo->prepare('INSERT INTO posts(title,slug,excerpt,seo_title,seo_description,canonical_url,content,cover_image,status,author_id,published_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
                $q->execute([$title,$slug,$excerpt,$seoTitle ?: null,$seoDescription ?: null,$canonicalUrl ?: null,$content,$cover ?: null,$status,$_SESSION['admin_id'],$publishAt]);
                $id = (int)$pdo->lastInsertId();
            }
            $pdo->prepare('DELETE FROM post_categories WHERE post_id=?')->execute([$id]);
            $insertCategory = $pdo->prepare('INSERT IGNORE INTO post_categories(post_id,category_id) SELECT ?,id FROM categories WHERE id=?');
            foreach ($categoryIds as $categoryId) $insertCategory->execute([$id,$categoryId]);
            $pdo->prepare('DELETE FROM post_tags WHERE post_id=?')->execute([$id]);
            $findTag=$pdo->prepare('SELECT id FROM tags WHERE slug=?');
            $createTag=$pdo->prepare('INSERT INTO tags(name,slug) VALUES(?,?)');
            $linkTag=$pdo->prepare('INSERT IGNORE INTO post_tags(post_id,tag_id) VALUES(?,?)');
            foreach ($tagNames as $tagName) {
                $tagSlug=slugify($tagName); $findTag->execute([$tagSlug]); $tagId=$findTag->fetchColumn();
                if (!$tagId) { try { $createTag->execute([$tagName,$tagSlug]); $tagId=(int)$pdo->lastInsertId(); } catch (PDOException $tagError) { $findTag->execute([$tagSlug]); $tagId=$findTag->fetchColumn(); if (!$tagId) throw $tagError; } }
                $linkTag->execute([$id,(int)$tagId]);
            }
            $pdo->commit();
            $fromStatus = (string)($oldPost['status'] ?? '');
            if ($fromStatus !== $status) {
                try { $ev=$pdo->prepare('INSERT INTO post_workflow_events(post_id,actor_id,from_status,to_status,note) VALUES(?,?,?,?,?)'); $ev->execute([$id,(int)$_SESSION['admin_id'],$fromStatus,$status, 'Status diubah melalui editor']); } catch (Throwable $ignored) {}
            }
            cache_clear();
            nowrd_audit($oldPost ?? false ? 'article.updated' : 'article.created', 'article', $id, $title);
            header('Location: '.base_url('admin/post.php?id='.$id.'&saved=1'));
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e instanceof PDOException && (($e->errorInfo[1] ?? null) === 1062)) $error = 'Slug sudah digunakan. Pilih slug yang berbeda.';
            else { error_log('NOWRD article save failed: '.$e->getMessage()); $error = 'Artikel belum dapat disimpan. Periksa log server jika masalah berlanjut.'; }
        }
    }
    $post = array_merge($post, ['title'=>$title,'slug'=>$slug,'excerpt'=>$excerpt,'seo_title'=>$seoTitle,'seo_description'=>$seoDescription,'canonical_url'=>$canonicalUrl,'content'=>$content,'cover_image'=>$cover,'status'=>$status,'published_at'=>$publishAt]);
}
if ($id && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    verify_csrf();
    require_post_permission($post, 'delete');
    $deletedTitleQ=$pdo->prepare('SELECT title FROM posts WHERE id=?'); $deletedTitleQ->execute([$id]); $deletedTitle=(string)($deletedTitleQ->fetchColumn() ?: '');
    $pdo->prepare('DELETE FROM posts WHERE id=?')->execute([$id]); cache_clear();
    nowrd_audit('article.deleted','article',$id,$deletedTitle);
    header('Location: '.base_url('admin/index.php'));
    exit;
}
$revisionHistory = [];
if ($id) {
    $historyQ = $pdo->prepare('SELECT id,title,status,created_at,created_by FROM post_revisions WHERE post_id=? ORDER BY created_at DESC,id DESC LIMIT 20');
    $historyQ->execute([$id]);
    $revisionHistory = $historyQ->fetchAll();
}
// Existing v4 articles were plain text. Convert them safely for editing while
// retaining line breaks; rich HTML saved by this version is sanitized first.
$editorContent = sanitize_rich_html((string)$post['content']);
if ($editorContent !== '' && strip_tags($editorContent) === $editorContent) {
    $editorContent = '<p>'.nl2br(e($editorContent), false).'</p>';
}
?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $id?'Edit story':'New story' ?> — NOWRD</title>
<link rel="stylesheet" href="<?=e(base_url('public/assets/style.css'))?>">
<style>.cover-input-row{display:flex;gap:9px;align-items:center}.cover-input-row input{flex:1;min-width:0}.media-cover-button{border:1px solid var(--line);background:#fff;border-radius:10px;padding:12px 13px;font:inherit;font-size:12px;font-weight:700;white-space:nowrap;cursor:pointer}.media-cover-button:hover{background:#f5f7f0}@media(max-width:560px){.cover-input-row{align-items:stretch;flex-direction:column}.media-cover-button{width:100%}}.taxonomy-fields{display:grid;grid-template-columns:1fr 1.4fr;gap:18px;padding:18px;border:1px solid var(--line);border-radius:14px;margin:12px 0 22px;background:#fbfcf9}.taxonomy-field{min-width:0}.category-checks{display:flex;gap:8px;flex-wrap:wrap}.check-chip{display:flex;align-items:center;gap:6px;border:1px solid var(--line);border-radius:999px;padding:7px 10px;font-size:12px;background:#fff;margin:0}.check-chip input{width:auto;margin:0}.taxonomy-field>input{width:100%}.taxonomy-field small{display:block;color:var(--muted);font-size:11px;margin-top:6px}@media(max-width:650px){.taxonomy-fields{grid-template-columns:1fr}}
</style>
</head>
<body class="editor-body">
<header class="editor-top"><a class="brand" href="<?=e(base_url('admin/index.php'))?>"><span class="brand-mark"><?=e(setting('brand_mark','n.'))?></span> <?=e(setting('brand_name','NOWRD'))?></a><span><?= $id?'EDIT STORY':'NEW STORY' ?></span><div class="editor-top-actions"><a class="edit-link" href="<?=e(base_url('admin/index.php'))?>">Cancel</a></div></header>
<main class="editor-wrap editor-wrap-wide">
<div class="eyebrow">THE JOURNAL / WRITING STUDIO</div>
<h1><?= $id?'Refine the story.':'A new story.' ?></h1>
<?php if($error): ?><div class="alert" role="alert"><?=e($error)?></div><?php endif; ?>
<?php if(isset($_GET['saved'])): ?><div class="success" role="status">Changes saved successfully.</div><?php endif; ?>
<?php if(isset($_GET['restored'])): ?><div class="success" role="status">Versi artikel berhasil dipulihkan. Versi sebelum pemulihan tetap tersimpan di riwayat.</div><?php endif; ?>
<form method="post" class="editor-form" id="storyForm">
<input type="hidden" name="_csrf" value="<?=e(csrf_token())?>">
<label for="storyTitle">Story title</label>
<input id="storyTitle" class="title-input" name="title" value="<?=e($post['title'])?>" required maxlength="220" placeholder="Give your story a title…">
<div class="slug-row"><div class="slug-field"><label for="storySlug">URL slug</label><input id="storySlug" name="slug" value="<?=e($post['slug'])?>" maxlength="240" placeholder="auto-generated-from-title"><small>Used in the article URL.</small></div><div class="slug-preview" id="slugPreview">/article/…</div></div>
<label for="storyExcerpt">Short description <span class="optional">(optional)</span></label>
<textarea id="storyExcerpt" name="excerpt" rows="2" maxlength="60000" placeholder="A sentence to invite readers in (aim for <?= (int)setting('default_excerpt_length','150') ?> characters)…"><?=e($post['excerpt'])?></textarea>
<label for="coverImage">Cover image URL <span class="optional">(optional)</span></label>
<div class="cover-input-row"><input id="coverImage" type="url" name="cover_image" value="<?=e($post['cover_image'])?>" placeholder="https://example.com/image.jpg"><button type="button" class="media-cover-button" id="chooseCover">Choose from Media Library</button></div>
<div class="taxonomy-fields"><div class="taxonomy-field"><label for="seoTitle">SEO title <span class="optional">(optional)</span></label><input id="seoTitle" name="seo_title" maxlength="220" value="<?=e($post['seo_title'] ?? '')?>" placeholder="Judul untuk mesin pencari"><small>Kosongkan untuk menggunakan judul artikel.</small></div><div class="taxonomy-field"><label for="seoDescription">SEO description <span class="optional">(optional)</span></label><input id="seoDescription" name="seo_description" maxlength="320" value="<?=e($post['seo_description'] ?? '')?>" placeholder="Ringkasan untuk hasil pencarian"><small>Disarankan sekitar 150–160 karakter.</small></div><div class="taxonomy-field" style="grid-column:1/-1"><label for="canonicalUrl">Canonical URL <span class="optional">(optional)</span></label><input id="canonicalUrl" type="url" name="canonical_url" maxlength="500" value="<?=e($post['canonical_url'] ?? '')?>" placeholder="https://domain.com/article/slug"><small>Gunakan hanya jika artikel memiliki URL utama yang berbeda.</small></div></div>
<div class="taxonomy-fields"><div class="taxonomy-field" style="grid-column:1/-1"><label for="publishAt">Waktu publikasi <span class="optional">(zona waktu situs)</span></label><input id="publishAt" type="datetime-local" name="published_at" value="<?=!empty($post['published_at']) ? e(date('Y-m-d\TH:i', strtotime($post['published_at']))) : ''?>"><small>Pilih waktu di masa depan untuk menjadwalkan artikel. Status harus “Publish story”. Kosongkan untuk menerbitkan sekarang.</small></div></div>
<div class="taxonomy-fields"><div class="taxonomy-field"><label>Categories</label><div class="category-checks"><?php if(!$allCategories): ?><small>No categories yet. <a href="<?=e(base_url('admin/taxonomies.php'))?>">Create one</a>.</small><?php else: foreach($allCategories as $cat): ?><label class="check-chip"><input type="checkbox" name="category_ids[]" value="<?= (int)$cat['id'] ?>" <?=in_array((int)$cat['id'],$selectedCategoryIds,true)?'checked':''?>> <?=e($cat['name'])?></label><?php endforeach; endif; ?></div></div><div class="taxonomy-field"><label for="postTags">Tags <span class="optional">(pisahkan dengan koma)</span></label><input id="postTags" name="tags" value="<?=e($selectedTagNames)?>" maxlength="2000" placeholder="contoh: catatan, desain, teknologi"><small>Tag baru dibuat otomatis saat artikel disimpan. Maksimal 20 tag per artikel.</small></div></div>
<div class="editor-label-row"><label>Story content</label><span class="editor-help">Changes are saved when you click Save story.</span></div>
<div class="writing-card">
<div class="editor-toolbar" role="toolbar" aria-label="Formatting tools">
<div class="toolbar-group"><button type="button" id="undoEdit" title="Undo (Ctrl+Z)" aria-label="Undo">↶</button><button type="button" id="redoEdit" title="Redo (Ctrl+Y)" aria-label="Redo">↷</button></div><span class="toolbar-divider"></span>
<div class="toolbar-group"><button type="button" data-command="bold" aria-label="Bold" title="Bold (Ctrl+B)"><b>B</b></button><button type="button" data-command="italic" aria-label="Italic" title="Italic (Ctrl+I)"><i>I</i></button><button type="button" data-command="underline" aria-label="Underline" title="Underline (Ctrl+U)"><u>U</u></button><button type="button" data-command="strikeThrough" aria-label="Strikethrough" title="Strikethrough"><s>S</s></button></div>
<span class="toolbar-divider"></span>
<div class="toolbar-group"><button type="button" data-block="p" title="Paragraph">¶</button><button type="button" data-block="h2" title="Heading 2">H2</button><button type="button" data-block="h3" title="Heading 3">H3</button><button type="button" data-block="h4" title="Heading 4">H4</button><button type="button" data-block="blockquote" title="Quote">❝</button></div>
<span class="toolbar-divider"></span>
<div class="toolbar-group"><button type="button" data-command="insertUnorderedList" aria-label="Bullet list" title="Bullet list">• List</button><button type="button" data-command="insertOrderedList" aria-label="Numbered list" title="Numbered list">1. List</button><button type="button" id="insertLink" aria-label="Insert link" title="Insert link">↗ Link</button><button type="button" id="insertImage" aria-label="Insert image" title="Insert image by URL">▧ URL image</button><button type="button" id="openMediaLibrary" title="Choose from Media Library">▣ Media</button><button type="button" data-command="insertHorizontalRule" title="Divider">—</button><button type="button" id="insertCode" title="Insert code block">{ }</button></div>
<div class="toolbar-spacer"></div><button type="button" id="focusToggle" class="preview-toggle" title="Hide distractions">Focus</button><button type="button" id="previewToggle" class="preview-toggle">Preview</button>
</div>
<div id="storyEditor" class="rich-editor" contenteditable="true" role="textbox" aria-label="Article content" aria-multiline="true" data-placeholder="Your story starts here…"><?= $editorContent ?></div>
<textarea name="content" id="contentValue" hidden><?=e($post['content'])?></textarea>
<div class="editor-status"><span id="wordCount">0 words</span><span id="readTime">1 min read</span><span id="charCount">0 characters</span><span class="status-saved" id="editorStatus">Ready to write</span></div>
</div>
<div class="editor-note" id="writingTip">Tip: Ctrl/Cmd+B bold · Ctrl/Cmd+I italic · Ctrl/Cmd+K link · Ctrl/Cmd+S save. A recovery copy is kept in this browser while you write.</div>
<div class="editor-actions"><select name="status" aria-label="Publication status"><option value="draft" <?=$post['status']==='draft'?'selected':''?>>Save as draft</option><?php if(current_admin_role()==='author'): ?><option value="in_review" <?=$post['status']==='in_review'?'selected':''?>>Submit for review</option><?php else: ?><option value="in_review" <?=$post['status']==='in_review'?'selected':''?>>In review</option><option value="approved" <?=$post['status']==='approved'?'selected':''?>>Approved</option><option value="published" <?=$post['status']==='published'?'selected':''?>>Publish story</option><?php endif; ?></select><button class="button" type="submit">Save story ↗</button></div>
</form>
<?php if($id && $revisionHistory): ?>
<section class="taxonomy-fields" style="display:block;margin-top:26px"><h2 style="margin:0 0 8px;font-size:20px">Riwayat versi</h2><p class="editor-note">Menampilkan hingga 20 versi terbaru. Pemulihan mengganti bidang artikel dan SEO; kategori serta tag tidak berubah.</p>
<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;font-size:13px"><thead><tr><th style="text-align:left;padding:9px">Judul versi</th><th style="text-align:left;padding:9px">Status</th><th style="text-align:left;padding:9px">Waktu</th><th style="padding:9px">Aksi</th></tr></thead><tbody>
<?php foreach($revisionHistory as $revisionRow): ?><tr><td style="padding:9px;border-top:1px solid var(--line)"><?=e($revisionRow['title'])?></td><td style="padding:9px;border-top:1px solid var(--line)"><?=e($revisionRow['status'])?></td><td style="padding:9px;border-top:1px solid var(--line)"><?=e(date('d M Y H:i',strtotime($revisionRow['created_at'])))?></td><td style="padding:9px;border-top:1px solid var(--line);text-align:center"><form method="post" onsubmit="return confirm('Pulihkan versi ini? Isi artikel saat ini akan disimpan sebagai versi riwayat terlebih dahulu.')"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="restore_revision" value="<?= (int)$revisionRow['id'] ?>"><button type="submit" class="media-cover-button">Pulihkan</button></form></td></tr><?php endforeach; ?>
</tbody></table></div></section><?php endif; ?>
<?php if($id): ?><form method="post" class="delete-form" onsubmit="return confirm('Delete this story permanently?')"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="delete" value="1"><button class="delete-button" type="submit">Delete story</button></form><?php endif; ?>
</main>
<script src="<?=e(base_url('public/assets/editor.js'))?>" defer></script>
<script>window.NOWRD_VISUAL = <?=json_encode(nowrd_visual_preferences(), JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>; window.NOWRD_VISUAL_ADMIN = true; window.NOWRD_CSRF = <?=json_encode(csrf_token())?>; window.NOWRD_VISUAL_SAVE_URL = <?=json_encode(base_url('admin/visual-settings.php'))?>;</script><script src="<?=e(base_url('public/assets/ui-v10.js'))?>" defer></script>
</body></html>
