# NOWRD CMS v24 — SEO Audit & Discoverability

## What's included
- Admin SEO Audit page at `admin/seo-audit.php` (requires `content.view`).
- Per-article heuristic checks for SEO title length, description/excerpt length, text length, headings, cover image, and canonical URL.
- Score and actionable links to edit each article.
- Existing dynamic XML sitemap and RSS feed are surfaced in the audit.
- No new database tables or migration required; uses existing post SEO fields.

## Important notes
- Scores are editorial heuristics, not search engine ranking predictions.
- This does not check external broken links, search console status, Core Web Vitals, or actual crawler indexing.
- Confirm your public canonical domain and HTTPS deployment settings before submitting the sitemap to search engines.

## Upgrade
1. Back up the current website and database.
2. Upload the v24 files while preserving `config.php` and `uploads/`.
3. Sign in with an account allowed to view content and open Admin → SEO Audit (`admin/seo-audit.php`).
4. Check `https://your-domain/sitemap.xml` and `https://your-domain/robots.txt` after deployment.
