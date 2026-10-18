# NOWRD v29 — Multilingual Publishing

## What's included
- Separate per-article translations for English, Spanish, French, and Japanese; Indonesian remains the source language.
- Admin translation editor reachable from Dashboard → Stories → Translate.
- Draft/published status per translation, separate translated slug, excerpt, SEO title/description, and content.
- Public article language switcher (ID / EN). Published translated slugs can also resolve directly with `?lang=<code>&slug=<translated-slug>`.
- Additive `post_translations` table created lazily by the translation admin/public article path; no existing article fields are overwritten.
- CSRF protection, permission check (`settings.manage`), prepared SQL statements, escaped form output, rich-content sanitization, and unique language/slug constraints.

## Use
1. Log in as an administrator.
2. In Dashboard → Stories, select **Translate** beside the article.
3. Choose a target language, fill in translated fields, and save as draft or publish.
4. Open the source article and use the ID / EN links. A translation is shown publicly only if it is published and the source article is published.

## Notes
- This version provides article-level translations, not automatic machine translation. Editors provide the translated text.
- Homepage/category listing labels and the full admin interface are not fully localized yet; Indonesian is the source/default site UI.
- If your database user cannot create tables, create `post_translations` using the equivalent schema in `admin/translations.php` or grant the required migration permission.
- Verify database backup and test the translation workflow on staging before production deployment.
