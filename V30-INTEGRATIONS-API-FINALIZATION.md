# NOWRD v30 — Integrations & API

## Delivered
- Versioned, read-only JSON API v1 for published article lists, individual published articles, and categories.
- Pagination and page-size caps; supported language filtering for Indonesian and published translations.
- Stable JSON error envelopes, GET-only enforcement, prepared statements, no public write endpoints, and no default cross-origin access.
- API endpoint index at `/api/` and usage documentation in `API-V1.md`.
- Release checklist and consolidated version notes.

## Deliberate boundaries
- No unauthenticated write API, admin API, webhook delivery, or API-key system is included; those require authentication, secret management, retries, and operational monitoring beyond a safe public content API.
- The website's default interface is still Indonesian; v29 provides article-level translations rather than full UI localization or automatic translation.
- Public APIs expose only public content fields. Keep private or confidential data out of published article bodies.

## Final deployment checks
- Run on staging with the intended PHP/MySQL versions.
- Confirm all endpoints return JSON for valid, invalid, and unavailable database cases.
- Verify `config.php` is not downloadable, HTTPS works, and backups are recoverable.
- Check theme, admin roles, publishing workflow, translation status, scheduled publishing, media upload, cache invalidation, and mobile layouts.
- Review PHP/server logs after staging tests. Passing syntax checks alone is not a production certification.
