<?php
require __DIR__ . '/app/bootstrap.php';
ensure_v8_schema();
header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=900');
$posts = cache_remember('public.sitemap.posts', nowrd_cache_ttl('cache_ttl_feeds', 120), static fn() => $pdo->query("SELECT slug, updated_at, published_at, created_at FROM posts WHERE status='published' AND (published_at IS NULL OR published_at<=NOW()) ORDER BY COALESCE(published_at,created_at) DESC")->fetchAll());
function xml_escape(string $value): string { return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8'); }
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
echo '<url><loc>' . xml_escape(base_url('index.php')) . '</loc></url>' . "\n";
foreach ($posts as $post) {
    $loc = base_url('article.php?slug=' . rawurlencode($post['slug']));
    $last = $post['updated_at'] ?: ($post['published_at'] ?: $post['created_at']);
    echo '<url><loc>' . xml_escape($loc) . '</loc><lastmod>' . date(DATE_W3C, strtotime($last)) . '</lastmod></url>' . "\n";
}
echo '</urlset>';
