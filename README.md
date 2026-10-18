# NOWRD — Publishing CMS starter

NOWRD adalah fondasi CMS editorial bergaya minimal-modern: website publik, artikel, dashboard admin, draft/publish, cover image via URL, dan setup admin awal. Dibangun dengan PHP 8+ dan MySQL/MariaDB agar mudah dipasang di shared hosting.

## Fitur saat ini
- Frontend responsive untuk halaman jurnal dan detail artikel.
- Dashboard admin dengan ringkasan konten.
- Login admin memakai `password_hash` / `password_verify`.
- CRUD artikel: buat, edit, hapus, draft, publish, slug, excerpt, cover image URL.
- Media Library: image upload (JPG/PNG/GIF/WebP/AVIF), random file names, MIME and image-dimension validation, alt text, copy URL, safe deletion checks, and direct insertion into article editor.
- Writing Studio rich-text: bold, italic, underline, strikethrough, headings, blockquote, bullet/numbered lists, links, divider, image via URL, preview, word count, reading time, dan shortcut Ctrl/Cmd+S.
- HTML artikel disanitasi server-side dengan allow-list sebelum disimpan dan ketika dirender; paste editor mengambil teks biasa untuk menghindari impor markup berbahaya.
- Prepared statements PDO, escaping output, token CSRF untuk formulir.
- SEO per artikel: SEO title, meta description, canonical URL, Open Graph dan Twitter cards.
- Sitemap XML (`/sitemap.php`) dan RSS 2.0 feed (`/rss.php`) untuk artikel terbit.
- Penjadwalan artikel menggunakan tanggal/waktu lokal situs; artikel terjadwal baru tampil setelah waktunya tiba.
- Migrasi kolom SEO otomatis dan idempotent untuk instalasi v7 yang sudah ada.
- Cookie sesi HttpOnly/SameSite, regenerasi ID sesi setelah login, header keamanan dasar.
- Pembatasan percobaan login berbasis database (maksimal 5 percobaan per IP/email dalam 15 menit).
- Validasi URL gambar hanya HTTP/HTTPS dan validasi batas panjang input.
- UI desktop dan mobile; tanpa build step / Composer.

## Persyaratan
- PHP 8.0+ (disarankan 8.2+), ekstensi PDO MySQL. mbstring direkomendasikan untuk hitungan karakter Unicode yang lebih akurat.
- MySQL 5.7+ atau MariaDB 10.4+.
- Apache/Nginx atau hosting PHP biasa.

## Instalasi lokal / hosting
1. Upload dan ekstrak project ke hosting.
2. Buka `/install.php`, lalu masukkan host, nama database, username, password database, nama situs, dan akun administrator.
3. Wizard mencoba menghubungkan database yang ada. Jika belum ada, wizard mencoba membuat database secara otomatis. Wizard juga membuat semua tabel dan data pengaturan awal.
4. Jika hosting menolak `CREATE DATABASE` (umum pada shared hosting), buat database dan pengguna melalui panel hosting (mis. cPanel → MySQL Databases), berikan pengguna hak `ALL PRIVILEGES`, lalu jalankan wizard lagi dengan nama database lengkap termasuk prefix hosting. Tidak perlu mengimpor `database.sql`; tabel dibuat oleh wizard.
5. Setelah instalasi sukses, hapus `install.php` dan login melalui `/admin/login.php`. Untuk keamanan, pastikan `config.php` tidak dapat diunduh melalui web.
6. Password administrator minimal 8 karakter; indikator warna membantu memperkirakan kekuatannya.
7. **Hapus `install.php` setelah instalasi berhasil.**
8. Masuk melalui `/admin/login.php` secara langsung; URL admin tidak ditautkan dari navigasi publik. Tautan admin tidak ditampilkan di navigasi publik; simpan URL ini secara pribadi.

## Media Library (v6)
- Admin menu: `/admin/media.php`. Existing v5 installations get the `media` table through a safe `CREATE TABLE IF NOT EXISTS` check; fresh installations also create it in the wizard.
- Images are limited to 8 MB and JPG, PNG, GIF, WebP, or AVIF. SVG is intentionally disallowed. Uploaded filenames are random; original filenames are metadata only.
- The editor toolbar opens a media picker. Selection is sent to the opener only from the same origin. Cover-image selection is also supported.
- The upload directory includes an `.htaccess` intended to prevent script execution on Apache/LiteSpeed; verify equivalent protections if using Nginx or another server.

## Struktur
- `index.php` — homepage jurnal
- `article.php` — detail artikel
- `admin/` — login, dashboard, editor
- `app/bootstrap.php` — koneksi PDO dan helper
- `public/assets/style.css` — desain responsive
- `database.sql` — skema database
- `config.example.php` — template konfigurasi

## Catatan keamanan / batasan starter
- Jangan commit atau membagikan `config.php`.
- Gunakan HTTPS dan password unik.
- Editor rich-text sekarang menyimpan markup terbatas yang disanitasi. Gambar dimasukkan melalui URL publik; role multi-user, revisions, dan API masih dapat ditambahkan pada iterasi berikutnya.
- Jika folder project ada di bawah `public_html`, blok akses ke file konfigurasi dan SQL dengan aturan server. Konfigurasi terbaik adalah document root mengarah ke folder aplikasi publik.
- Ini merupakan starter yang bisa dikembangkan, belum dimaksudkan sebagai pengganti penuh Ghost CMS pada tahap ini.


## v7 — Content organization
- Article categories and tags with many-to-many relations.
- Search and pagination in the admin article list, plus status/category filters.
- Public search and category filters.
- Taxonomy management at `/admin/taxonomies.php`.
- Existing v5/v6 installations create taxonomy tables idempotently when the relevant pages are opened; the database user must have CREATE TABLE privileges.
- Tags can be entered in the article editor as comma-separated names (maximum 20 per article).


## v8 — SEO, feeds, dan scheduled publishing
- Isi SEO title, SEO description, dan canonical URL di editor artikel.
- Artikel publik menampilkan metadata description, canonical, Open Graph, dan Twitter cards.
- Sitemap tersedia di `/sitemap.php`; RSS feed tersedia di `/rss.php`. Keduanya hanya menyertakan artikel yang sudah waktunya terbit.
- Pilih status “Publish story” dan isi “Waktu publikasi” untuk menjadwalkan artikel. Waktu kosong berarti terbit sekarang. Zona waktu mengikuti konfigurasi situs (`timezone`, default `Asia/Jakarta`).
- Instalasi v7 yang sudah ada akan menambahkan kolom SEO ketika halaman publik/editor yang relevan dibuka. Pastikan user database punya izin `ALTER TABLE` dan `INSERT` pada `settings`. Backup database sebelum deploy.
- `robots.txt` menunjuk ke sitemap. Jika situs berada di subfolder, sesuaikan baris Sitemap agar menyertakan path lengkap situs.


## v9 — Editorial workflow & revision history
- Setiap kali artikel lama disimpan, versi sebelumnya dicadangkan ke riwayat secara otomatis.
- Pulihkan versi lama dari panel editor; kondisi artikel tepat sebelum pemulihan juga disimpan sebagai versi baru.
- Riwayat menyimpan field artikel dan SEO; kategori/tag tetap mengikuti relasi saat ini.
- Migrasi membuat tabel `post_revisions` secara idempotent saat editor artikel dibuka. Pastikan user database memiliki izin `CREATE TABLE`.
- Perbaikan validasi jadwal publikasi: status dibaca sebelum tanggal publikasi diproses, dan tanggal yang tidak valid ditolak.
- Riwayat versi saat ini tidak menggantikan backup database. Tetap lakukan backup rutin sebelum deployment.

## v10 — Classic & Modern UI Preferences

NOWRD CMS v10 adds an optional monochrome modern workspace while keeping the existing Classic interface available. On authenticated admin pages, use the **Tampilan** control to switch between modes. The preference is saved in the current browser only and does not change other administrators' settings.

- **Classic:** retains the existing sidebar and admin/editor styling.
- **Modern:** uses a restrained black, white and gray palette, rounded editorial panels, subtle glass effects, and a centered bottom navigation dock.
- **Dock transparency:** adjustable from 20% to 96% opacity.
- **Dock blur:** adjustable from 0 to 40 px.
- **Responsive behavior:** dock and appearance panel adapt to smaller mobile screens and safe-area insets.
- **Reset:** restores Classic mode, 72% dock opacity, and 22 px blur.

UI preferences are stored in `localStorage` under `nowrd-ui-v10`; they do not require database changes. The public-facing website and its existing Ghost-inspired editorial concept remain unchanged. The modern style is intentionally monochrome and does not alter article content or database records.

### v10 verification

Run `php -l` on PHP files and a JavaScript syntax check on `public/assets/ui-v10.js` before deployment. Test both modes on desktop and mobile, including the appearance panel sliders. Back up the existing installation before replacing files on production hosting.


## v11 — Writing Studio Plus

- Editor controls include undo/redo, H4 heading, and code-block insertion, alongside existing formatting, lists, links, images, media-library picker, divider, and preview.
- Focus mode reduces editing distractions while leaving the story editor and save controls available.
- Word count, estimated reading time, and character count update as you write.
- Browser-local recovery snapshots are stored in localStorage and offered after an interrupted session. They remain on that browser/device only; use **Save story** to persist content to the server.
- Keyboard shortcuts: Ctrl/Cmd+B bold, Ctrl/Cmd+I italic, Ctrl/Cmd+K link, Ctrl/Cmd+S save.
- H4 is now accepted by the server-side rich-content sanitizer and renderer.

Browser-local recovery is a convenience, not a replacement for saving to the database. Clear browser storage to remove any locally retained recovery copies.

## v12 — Advanced Display & Branding

- Added a dashboard Settings page at `admin/settings.php` for persistent brand name, text mark, tagline, canonical site URL, and site description. Values are stored in the existing `settings` table and escaped on output.
- Expanded the display preferences panel with monochrome Light/Dark/System themes, three dock sizes, five navigation positions, adjustable transparency and blur, plus reset controls.
- Display preferences are stored in the current browser via localStorage; branding settings are stored in the server database.
- Added responsive dock layouts and dark-theme styling for dashboard, taxonomy, settings, and writing surfaces.
- Existing v11 content, writing studio, taxonomy, SEO, revisions, scheduling, and media features are retained.

Validation for v12: PHP syntax checks passed for all PHP files, JavaScript syntax check passed, and the ZIP archive integrity check passed. Browser visual tests and live database/hosting tests have not been performed.


## v13 — Editorial identity & sustainable workspace

- Expanded admin Settings with publication identity, mission, editorial principles, default author reference, dashboard page size, and excerpt-length guidance.
- Settings are stored in the existing `settings` table; no manual database migration is required for this version.
- Dashboard story pagination uses the configured page size.
- The dashboard can display the editorial mission as a quiet workspace reminder.
- Destructive taxonomy actions respect the confirmation preference.
- Settings can be exported to a JSON file for documentation/recovery. This is a settings-only export, not a database or media backup.
- Public branding uses the saved publication name, monogram, and tagline.
- Existing articles and author assignments are not rewritten by changing settings.


## v14 — Maintenance & Editorial Workflow

- Admin Maintenance page provides lightweight runtime checks, content inventory, recent admin activity, and a structured JSON content export.
- Audit trail records article create/update/delete/revision restore and settings updates. It deliberately excludes article bodies, passwords, and raw IP addresses.
- Content JSON export includes article/taxonomy/settings/media metadata/revision rows; it excludes users/password hashes, `config.php`, database credentials, and uploaded binary files. It is not a complete backup or one-click restore.
- Before upgrades, separately back up the database, `config.php`, and the entire `uploads/` directory.
- Maintenance diagnostics are request-time checks, not background monitoring.


## v15 — Security, Backup & Recovery

- Admin sessions expire after 30 minutes of inactivity.
- Login successes, failed login attempts, and logout events are recorded in the admin activity log.
- Maintenance includes a SQL database dump export. The dump includes database rows (including password hashes) and must be stored privately. It excludes `config.php` and uploaded file binaries.
- SQL dump uses `DROP TABLE IF EXISTS`; restore only into a separate or empty database after reviewing the file. Do not import it into the active production database.
- Uploaded media under `uploads/` must be backed up separately. This version does not implement automatic SQL restore.

## v15 — Security, Backup & Recovery

- Admin sessions expire after 30 minutes of inactivity.
- Successful logins, failed login attempts, and logout events are recorded in the admin activity log.
- Maintenance includes a SQL database dump export. The dump includes database rows (including password hashes) and must be stored privately.
- The SQL dump uses `DROP TABLE IF EXISTS`; restore only into a separate or empty database after reviewing the file. Do not import it into the active production database.
- Uploaded media under `uploads/` and `config.php` are not included in the SQL dump and must be backed up separately.
- Automatic restore is not included in this version.


## v16 — Admin Control Center

- Added `admin/users.php` for creating admin accounts, changing the current administrator's password, and removing another admin account.
- Requires CSRF verification and current-password verification for password changes; new passwords must be at least 12 characters.
- Protects the signed-in account and prevents deleting the final administrator account.
- Adds an Admin accounts navigation link in the existing admin sidebar.
- Account actions are recorded through the existing `nowrd_audit()` helper when the audit table is available.

All accounts have equal administrator privileges in this version; role-based permissions and forced password reset on first login are not implemented. Test account creation and password changes against a staging database before production deployment.

## v17 — Role-Based Access Control

NOWRD now supports Administrator, Editor, and Author roles. The additive migration adds a `role` column to the existing `users` table when a protected admin route is first visited. Existing users retain Administrator access by default. Review `ROLE-BASED-ACCESS.md` before assigning roles and test with separate accounts before production use.


## v18 — Editorial Workflow & Shared-hosting Cache

This release adds a lightweight file-backed query cache for public pages. It does not require Redis, Memcached, APCu, a queue worker, or a cron job. Cache data is written to the PHP system temporary directory when writable; if unavailable, NOWRD attempts a protected `storage/cache` directory. Public listing/category/article/RSS/sitemap reads are cached briefly (45–120 seconds), while settings are memoized for the current request. Cache generation is invalidated after article and settings/taxonomy changes. On shared hosting the cache can be disabled by making the cache directory unwritable; the site falls back to direct queries.

Editorial workflow statuses are `draft`, `in_review`, `approved`, and `published`. Authors can submit work for review but cannot publish directly; editors and administrators can approve or publish. Only `published` content appears on public pages. Existing posts keep their status during the additive schema migration. The migration updates the status enum on posts and revisions and creates `post_workflow_events` if permissions allow ALTER/CREATE TABLE.

### Standalone installation
1. Upload the `NOWRD` folder to a PHP hosting account with MySQL/MariaDB and PDO MySQL enabled.
2. Visit `/install.php` and provide the database details and initial administrator credentials. On shared hosting, create the database/user in the hosting panel first if `CREATE DATABASE` is restricted.
3. Ensure the project folder can write `config.php` during setup. After installation, remove `install.php` or restrict it at the server level.
4. Keep `config.php`, database dumps, and cache files private. Back up the database and `uploads/` independently.

Cache is an optimization, not a durable data store. A cache miss must always be safe; clearing temporary files should never delete article data. Test the installer and database migration on a staging copy before updating a production site.

## v19 — Performance & Deployment Pro

This release extends the v18 shared-hosting cache with cache hit/miss/write counters and a Maintenance dashboard for cache TTL tuning. In Maintenance, administrators with `maintenance.manage` can clear the active cache version and set TTLs for article listings (15–300 seconds), article pages (15–600 seconds), category lists (15–600 seconds), and RSS/sitemap reads (30–1800 seconds). Settings are stored in the existing settings table; no extra database migration is required. Cache counters are best-effort local diagnostics and can be reset by hosting cleanup. They are not a benchmark and do not measure database query duration.

The deployment health check now reports PHP version, PDO MySQL, JSON, Fileinfo, optional mbstring, upload/post limits, memory limit, configuration presence, upload/cache directory access, and HTTPS detection. These checks reflect the current PHP request and cannot validate the hosting firewall, remote DNS, mail delivery, or every database permission. For production upgrades, back up the database, `config.php`, and `uploads/`, then test on staging first. To roll back, restore the previous code archive and its matching database backup; do not assume an older code version understands newer schema changes.

## v20 — Visual refinement

The admin display control (floating dock or launcher) now includes Classic and Glassmorphism modes, selectable visual palettes (Classic, Glass, Ocean Blue, Purple Dream, Forest Green, Sunset), light/dark/system appearance, dock size and placement, adjustable blur/transparency, and motion intensity (Off, Subtle, Smooth, Playful). Preferences are stored locally in the browser and are per-device; they do not alter content or database records. The interface respects `prefers-reduced-motion` and offers an Off setting. Glass effects depend on browser support for `backdrop-filter`.


## v21 — Global Visual Settings

Visual preferences are stored centrally in the `settings` table and apply to both the public website and admin studio. Only users with settings permission can save global preferences. See `V21-GLOBAL-VISUAL-SETTINGS.md`.

## v23 Complete Theme Engine
See `V23-COMPLETE-THEME-ENGINE.md` for the global public theme refinements, motion accessibility, and compatibility notes.

## v24 — SEO Audit & Discoverability

Open **Admin → SEO Audit** (`admin/seo-audit.php`) for per-article checks covering title/description length, content length, headings, cover images, and canonical URL format. The audit is a practical editorial checklist, not a ranking guarantee. Dynamic sitemap and RSS endpoints remain available. See `V24-SEO-AUDIT.md` for upgrade notes.

## Media Library Pro (v25)
The Media Library now supports server-side search by filename/alt text, orientation and missing-alt filters, sort order, and pagination (24 items per page). Existing upload validation, alt-text editing, picker integration, and protection against deleting media used by articles remain in place. See `V25-MEDIA-LIBRARY-PRO.md` for upgrade instructions.


## v26 Security & Recovery
See `V26-SECURITY-RECOVERY.md` for the security overview, deployment notes, and recovery checklist.

## v29–v30: Multilingual Publishing and Content API

- Article translations are managed from **Dashboard → Stories → Translate**. Indonesian remains the source language; English, Spanish, French, and Japanese translations can be saved as drafts or published. See `V29-MULTILINGUAL-PUBLISHING.md`.
- Public read-only JSON endpoints are documented in `API-V1.md`: `/api/v1/posts.php`, `/api/v1/post.php`, and `/api/v1/categories.php`. The API returns published content only and caps list page size at 50.
- Release notes and staging checklist: `V30-INTEGRATIONS-API-FINALIZATION.md`.

---

## Repository & contribution notes

- Current repository snapshot: **NOWRD CMS v30**.
- Version history summary: see [`CHANGELOG.md`](CHANGELOG.md).
- API documentation: [`API-V1.md`](API-V1.md).
- Testing checklist and known manual checks: [`TESTING.md`](TESTING.md).
- Access model: [`ROLE-BASED-ACCESS.md`](ROLE-BASED-ACCESS.md).

### Requirements

- PHP 8.0+ (PHP 8.2+ recommended), with PDO MySQL; `mbstring` is recommended.
- MySQL 5.7+ or MariaDB 10.4+.
- A web server configured to execute PHP. If `.php` files are downloaded instead of executed, fix the hosting PHP handler/domain configuration before troubleshooting application code.

### Public-repository safety checklist

Before deploying this source to a live website:

1. Keep the real `config.php` out of Git; only `config.example.php` belongs in source control.
2. Never commit database dumps containing real user/content data, passwords, API tokens, private keys, backups, or production uploads.
3. Run `install.php` only during initial setup, then remove it from the live hosting account as instructed by the installer.
4. Ensure HTTPS is enabled and the host denies direct access to configuration and backup files.
5. Test on staging and review server logs before production deployment.

This repository currently has no explicit open-source license. Public visibility on GitHub does not by itself grant permission to reuse, redistribute, or modify the code. Add a license only after choosing the intended terms.

### Push this project to GitHub

1. Create a **new empty repository** on GitHub, for example `nowrd-cms`. Choose Public. Do not initialize it with a README, license, or `.gitignore` because those files are already in this project.
2. Download/unzip this repository package locally, then open a terminal in this project directory.
3. Run:

   ```bash
   git init
   git add .
   git status --short
   git commit -m "chore: publish NOWRD CMS v30 source and history"
   git branch -M main
   git remote add origin https://github.com/YOUR-USERNAME/nowrd-cms.git
   git push -u origin main
   ```

4. Replace `YOUR-USERNAME` with your GitHub username and `nowrd-cms` with the repository name you created. Authenticate through GitHub's browser/device flow or a supported credential manager; do not put passwords or tokens directly in the remote URL.
5. Before `git commit`, inspect `git status --short` and confirm no `config.php`, `.env`, backup, or private upload files are staged.

If you prefer the GitHub website, create the empty public repo, then use **Add file → Upload files** to upload the extracted project files. The Git CLI method is better for ongoing updates and proper version history.
