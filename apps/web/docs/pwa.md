# Progressive Web App

Finba.se is online-first and installable as a Progressive Web App. Financial data is not edited or mutated offline.

## Public assets

- `/manifest.webmanifest`
- `/service-worker.js`
- `/offline.html`
- Favicon and `/pwa/*` icons under `public/`

## Behavior

- Conservative service worker with precache limited to public static assets
- Authenticated financial HTML is not cached for offline mutation
- Offline fallback page at `/offline.html`
- Install affordance in the application top bar
- Explanatory install modal before the browser install UI
- At most one proactive install suggestion per browser session
- iOS Safari uses manual “Add to Home Screen” instructions
- Service worker updates require explicit user confirmation

Browser storage is limited to UI flags in `sessionStorage` (install suggestion, update dismissal, release banner). Do not store financial records in Cache Storage, IndexedDB, or Background Sync.

Deferred: native packaging, push notifications, and offline sync.

## Cache headers

Production must not treat `service-worker.js` or `manifest.webmanifest` as immutable forever.

`docker/Caddyfile` and Laravel routes in `routes/web.php` serve:

- `/service-worker.js` with `Cache-Control: no-cache`
- `/manifest.webmanifest` with the correct manifest MIME type and `no-cache`
- `/offline.html` with `no-cache`

### Apache

`public/.htaccess` already sets `Cache-Control: no-cache` for the service worker and manifest, plus the correct manifest content type.

### Nginx / Cloudflare

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

If Cloudflare caches these paths aggressively, bypass the cache or use a short TTL.

## Laravel routes

```text
GET /manifest.webmanifest
GET /service-worker.js
GET /offline.html
```

These routes are useful for local testing and for environments where the front controller serves the assets.

## Hosting notes

On the public host (`https://app.finba.se`), confirm:

- Manifest `start_url` resolves on the custom domain
- Service worker updates are not stuck behind long-lived CDN caches
- Offline fallback appears when the network is unavailable
- Offline mode does not present unsafe stale financial editing
