# NOWRD CMS v25 — Media Library Pro

## Included
- Search media by original filename, stored filename, or alternative text.
- Filter by landscape, portrait, square, and missing alt text.
- Sort newest, oldest, or largest files first.
- Server-side pagination (24 records per page) to avoid loading the entire library at once.
- Existing safe image validation, alt-text editing, copy URL, article picker, audit events, and deletion guard are retained.
- No new database tables or migration required.

## Upgrade
1. Back up the website, database, `config.php`, and `uploads/` directory.
2. Upload the v25 files over the existing NOWRD installation, preserving local `config.php` and uploaded media.
3. Open Admin → Media Library and test search, filters, sort order, pagination, upload, alt-text save, article picker, and deletion guard.

## Limitations
This version improves library management but does not generate resized thumbnails or re-encode image files. Actual browser and hosting tests should be completed on staging before production deployment.
