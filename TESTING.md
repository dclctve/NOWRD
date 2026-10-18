# NOWRD v8 testing checklist

## Static checks performed
- Run `php -l` on every PHP file.
- Run `node --check` on `public/assets/editor.js` when Node is available.
- Inspect the ZIP archive with `unzip -t`.

## Manual database tests required before production
1. Login and open Categories & Tags; confirm taxonomy tables auto-create on an existing v6 database.
2. Create category and tag; reject duplicate slug cleanly.
3. Create/edit article with multiple categories and comma-separated tags; reload and confirm selections persist.
4. Delete category/tag and verify posts remain and mappings cascade.
5. Search admin stories by title/content; filter by status and category; test pagination.
6. Search public stories and select a category; ensure drafts never appear publicly.
7. Test mobile viewport and keyboard navigation.


## v8 static checks
- Run `php -l` on all PHP files.
- Run `node --check public/assets/editor.js` if Node is available.
- Run `unzip -t` on the release ZIP.

## Manual checks required on a staging database
1. Open the existing v7 site and confirm the SEO columns are added without changing existing articles.
2. Edit an article with SEO title/description/canonical URL; verify meta tags in page source.
3. Publish an article in the future; verify it is absent from homepage, article URL, sitemap, and RSS until its scheduled time.
4. Verify `/sitemap.php` returns XML and `/rss.php` returns RSS 2.0 XML with escaped special characters.
5. Publish an article immediately and verify it appears in homepage, sitemap, and RSS.
6. Test invalid canonical URL and invalid date input; confirm the article is not saved.
7. Test on staging with the hosting timezone and DB timezone aligned. Public visibility checks use MySQL `NOW()`, so ensure the database server timezone matches the configured site timezone.


## v9 staging checks
1. Buka editor artikel v8 yang sudah ada; pastikan tabel `post_revisions` dibuat tanpa mengubah artikel lama.
2. Ubah dan simpan artikel; pastikan versi sebelum perubahan muncul di Riwayat versi.
3. Pulihkan versi sebelumnya; pastikan artikel kembali ke nilai tersimpan dan versi sebelum pemulihan tetap masuk riwayat.
4. Pastikan pemulihan hanya tersedia untuk admin dan POST tanpa token CSRF ditolak.
5. Uji jadwal dengan waktu masa depan dan waktu yang tidak valid; artikel masa depan tidak boleh tampil publik, sitemap, atau RSS sampai waktunya.
6. Uji slug duplikat, artikel baru, artikel draft, dan artikel terbit biasa.

## v15 — Security, Backup & Recovery checks

- [x] PHP syntax checked for all PHP files with `php -l`.
- [x] JavaScript syntax checked with `node --check` for admin UI and editor scripts.
- [x] ZIP integrity checked with `unzip -t` after packaging.
- [ ] Live login/session-expiry behavior must be verified on the target hosting environment.
- [ ] SQL backup generation and restore must be tested against a staging database before production use.
- [ ] Back up `config.php`, the database, and `uploads/` independently before deployment.

The SQL export contains database rows including password hashes, and emits DROP TABLE statements. Store it privately and restore only into a separate/empty database after reviewing it. Uploaded media files and configuration are not included.


## v18 workflow and cache checks
- Confirm public index, article, RSS, and sitemap continue to show only published stories.
- Save an article as draft, in review, approved, then published using permitted roles.
- Verify an Author can submit for review but cannot publish directly.
- Edit a published story and confirm cache invalidation updates the public page within the next request.
- Test with system temp writable and unwritable; public pages must continue working if cache storage is unavailable.
- On a staging database, confirm the additive status migration and workflow event table succeed before production rollout.

## v19 cache and deployment diagnostics

- Open Admin → Maintenance as an administrator and confirm the PHP / extension / writable-directory diagnostics display.
- Open the public homepage and an article twice, then revisit Maintenance and confirm cache hit/miss/write counters update. The first request may miss; a subsequent request within TTL should hit.
- Change each cache TTL within the displayed limits, save, and confirm the values persist after reload. Submit out-of-range values manually and confirm server-side clamping.
- Clear the active cache and confirm the public site still loads and cache misses repopulate the cache.
- Confirm no credentials or cache file contents are rendered on the dashboard. Cache stats are diagnostic only and may be affected by concurrent requests or filesystem permissions.
- On staging, verify PDO MySQL and media upload requirements against the hosting PHP configuration. CLI lint does not prove the web server has the same PHP extensions.
- Deployment rollback: keep the previous code archive, a database dump, `config.php`, and `uploads/` backup together. Test restoration only in an isolated database/site first.

## v24 SEO Audit checks
- [ ] Open `admin/seo-audit.php` as an account with `content.view` permission.
- [ ] Verify published and draft articles appear and edit links open the correct article.
- [ ] Check scores for missing/short metadata, headings, and cover images.
- [ ] Confirm `/sitemap.xml` and `/robots.txt` resolve on the target Apache hosting.
- [ ] Validate representative article URLs with a search engine structured-data/URL inspection tool after deployment.

## v25 Media Library Pro checks
- [ ] Search by original filename and alt text.
- [ ] Filter landscape, portrait, square, and missing alt text.
- [ ] Sort newest, oldest, and largest.
- [ ] Navigate pagination while retaining search/filter state.
- [ ] Verify picker selection still returns media to the article editor.
- [ ] Verify used media cannot be deleted.
- [ ] Verify upload and alt-text edits on staging.


## v26 security checks
- [ ] Open `admin/security.php` as an administrator; confirm non-admin users receive a 403 response.
- [ ] Verify login throttling on a staging copy without locking out the production administrator.
- [ ] Confirm old password hashes rehash successfully after a valid login.
- [ ] Test upload behavior for normal images and verify script extensions are not executable.
- [ ] Restore database and `uploads/` to a separate staging site and verify content.
- [ ] On Nginx, configure equivalent deny rules because `.htaccess` is Apache-specific.
