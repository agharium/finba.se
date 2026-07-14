# PWA hosting notes

## Required public files

- `/manifest.webmanifest`
- `/service-worker.js`
- `/offline.html`
- Favicon and `/pwa/*` icons already under `public/`

## Apache

`public/.htaccess` already sets:

- `Cache-Control: no-cache` for `service-worker.js` and `manifest.webmanifest`
- `Content-Type: application/manifest+json` for `.webmanifest`

## Nginx / Cloudflare (manual)

Ensure production does **not** treat `service-worker.js` as immutable forever.

Recommended:

```nginx
location = /service-worker.js {
    add_header Cache-Control "no-cache";
    default_type application/javascript;
}

location = /manifest.webmanifest {
    add_header Cache-Control "no-cache";
    default_type application/manifest+json;
}
```

If Cloudflare caches these aggressively, bypass or set short TTL for those two paths.

## Laravel routes

`routes/web.php` also exposes:

- `GET /manifest.webmanifest`
- `GET /service-worker.js`
- `GET /offline.html`

with `Cache-Control: no-cache` and correct content types. Useful for local testing and environments where the front controller serves these paths.
