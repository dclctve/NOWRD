<?php
require __DIR__ . '/app/bootstrap.php';
ensure_v8_schema();
header('Content-Type: application/rss+xml; charset=UTF-8');
header('Cache-Control: public, max-age=900');
$siteName = setting('site_name', $config['site_name'] ?? 'NOWRD');
$description = setting('site_description', setting('site_tagline', 'Latest stories from NOWRD.'));
$posts = cache_remember('public.rss.posts', nowrd_cache_ttl('cache_ttl_feeds', 120), static fn() => $pdo->query("SELECT p.title,p.slug,p.excerpt,p.content,p.published_at,p.created_at,p.updated_at FROM posts p WHERE p.status='published' AND (p.published_at IS NULL OR p.published_at<=NOW()) ORDER BY COALESCE(p.published_at,p.created_at) DESC LIMIT 30")->fetchAll());
function rss_xml(string $value): string { return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8'); }
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<rss version="2.0"><channel>';
echo '<title>' . rss_xml($siteName) . '</title><link>' . rss_xml(base_url('index.php')) . '</link><description>' . rss_xml($description) . '</description><language>id-ID</language>';
foreach ($posts as $post) {
    $url = base_url('article.php?slug=' . rawurlencode($post['slug']));
    $summary = trim((string)$post['excerpt']);
    if ($summary === '') $summary = text_substr(rich_html_to_text((string)$post['content']), 0, 400);
    $date = strtotime($post['published_at'] ?: $post['created_at']);
    echo '<item><title>' . rss_xml($post['title']) . '</title><link>' . rss_xml($url) . '</link><guid isPermaLink="true">' . rss_xml($url) . '</guid><description>' . rss_xml($summary) . '</description><pubDate>' . date(DATE_RSS, $date) . '</pubDate></item>';
}
echo '</channel></rss>';
