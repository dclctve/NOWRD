# NOWRD Content API v1

NOWRD exposes a small, read-only JSON API for public published content. Base path: `/api/v1/`. Endpoints are public and require no token because they only return already-published public content. There are no write, login, or admin endpoints in this API.

## Endpoints

### `GET /api/v1/posts.php`
Returns a paginated list of published articles. Query parameters:
- `page`: page number, minimum 1 (default 1)
- `limit`: page size 1–50 (default 10)
- `lang`: `id`, `en`, `es`, `fr`, or `ja` (default `id`); non-Indonesian results include only published translations

Example: `/api/v1/posts.php?page=1&limit=10&lang=id`

### `GET /api/v1/post.php?slug=...&lang=id`
Returns a single published article including body content. For translations, pass the translated slug and language code, e.g. `?slug=hello-world-en&lang=en`.

### `GET /api/v1/categories.php`
Returns categories that currently contain published articles, with public article counts.

### `GET /api/`
Returns a JSON endpoint index.

## Response and behavior
- Success responses use a `data` property and list responses include `meta`.
- Errors use an `error` object with a stable `code` and a safe message.
- Only GET is allowed; other methods return HTTP 405.
- List page size is capped at 50. Large offsets are rejected.
- SQL values use prepared statements. Draft, in-review, future-scheduled, and unpublished translations are not exposed.
- Responses use JSON UTF-8, `X-API-Version: 1`, `nosniff` inherited from the application bootstrap, and a short public cache header.
- CORS is not enabled by default. Configure it at the web server only if a known external frontend needs it.
- This is a read-only content API, not a webhook system. Do not add secrets or private user information to public article content.

## Example with JavaScript

```js
const response = await fetch('/api/v1/posts.php?page=1&limit=10');
if (!response.ok) throw new Error('NOWRD API request failed');
const result = await response.json();
console.log(result.data, result.meta);
```

## Deployment checklist
1. Configure NOWRD normally and test in staging first.
2. Ensure the database account can create the translation table if multilingual API endpoints are used before the admin translation editor has created it.
3. Keep `config.php` private and HTTPS enabled.
4. Add restrictive CORS rules only when needed, and apply server-level rate limits if the API receives substantial traffic.
5. Test API endpoints with real published/draft/scheduled records before production.
