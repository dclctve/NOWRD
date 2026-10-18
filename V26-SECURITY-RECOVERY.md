# NOWRD CMS v26 — Security & Recovery

## What's included
- Adds an administrator-only Security & Recovery overview at `admin/security.php`.
- Shows basic runtime checks for HTTPS detection, PHP version, session strict mode, HTTP-only cookie setting, password APIs, and CSRF helpers.
- Shows recent authentication audit events and the login-attempt count when the optional tables are available.
- Upgrades older password hashes after a successful login when PHP recommends rehashing.
- Adds a response security header and denies common backup/config artifact extensions in Apache `.htaccess`.
- Adds an `uploads/.htaccess` rule to deny direct requests for server-side script extensions.

## Install
1. Back up the database, `config.php`, and the full `uploads/` directory.
2. Upload the files while preserving the existing project structure. Keep your production `config.php`.
3. Sign in with an administrator account and open `admin/security.php`.
4. Verify login, article editing, uploads, and public pages.

## Important limitations
- This is a defensive baseline, not a penetration test or security certification.
- `.htaccess` rules apply only when Apache/LiteSpeed honors them; Nginx users must configure equivalent rules in the server configuration.
- HTTPS detection can be affected by reverse proxies. Configure trusted proxy headers at the host; do not trust arbitrary forwarded headers blindly.
- Backups must be tested by restoring to a separate database and restoring media separately. This page does not automatically restore backups.
- Ensure the hosting account has appropriate file permissions and keep `config.php`, SQL dumps, and backups outside public access.
