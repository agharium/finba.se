# Supabase Storage (Finba production files)

Finba stores private application files through Laravel's filesystem abstraction.

- Local development: `FINBA_STORAGE_DISK=local`
- Production: `FINBA_STORAGE_DISK=finba` (S3-compatible Supabase Storage)

Business code never talks to Supabase SDKs directly. It always uses:

```php
Storage::disk(config('finba.storage.disk'))
```

## Why S3 protocol

Server-side uploads use Supabase Storage's **S3-compatible API** with Laravel's native S3 driver (`league/flysystem-aws-s3-v3`).

Do **not**:

- use the Supabase JavaScript client for server uploads;
- expose S3 credentials to the browser / Vite;
- reuse database or service-role keys as storage credentials;
- make the Finba bucket public.

## Dashboard setup

1. Open your Supabase project.
2. Create a **private** bucket named `finba` (recommended).
3. Open **Storage → Configuration / S3**.
4. Enable the S3 protocol if the project requires it.
5. Generate an S3 access key + secret (server-only credentials).
6. Copy from the dashboard:
   - endpoint (do not append the bucket name)
   - region
   - access key ID
   - secret access key
7. Configure production environment variables (see below).
8. Set `FINBA_STORAGE_DISK=finba`.

### Endpoint shape

Use exactly the endpoint shown by Supabase. Common forms:

```text
https://<project-ref>.storage.supabase.co/storage/v1/s3
https://<project-ref>.supabase.co/storage/v1/s3
```

Laravel disk `finba` sets `use_path_style_endpoint` to `true`, which is required for this provider.

## Environment variables

```env
FINBA_STORAGE_DISK=finba

SUPABASE_STORAGE_ACCESS_KEY_ID=
SUPABASE_STORAGE_SECRET_ACCESS_KEY=
SUPABASE_STORAGE_REGION=
SUPABASE_STORAGE_BUCKET=finba
SUPABASE_STORAGE_ENDPOINT=https://<project-ref>.storage.supabase.co/storage/v1/s3
```

Local:

```env
FINBA_STORAGE_DISK=local
```

## Security

- S3 credentials are **infrastructure secrets**.
- They may bypass normal Storage RLS; protect them like production keys.
- Never commit them.
- Rotate immediately if exposed.
- Persist only object **paths** in the database (never signed/public URLs).

## Object paths

Domain directories on the configured disk:

```text
feedback/{feedback_uuid}/{generated-filename}.webp
```

Future domains may include `avatars/`, `receipts/`, `imports/`, `attachments/`.

## Cloud Run / stateless hosts

Container filesystems are disposable. Production uploads must use `FINBA_STORAGE_DISK=finba`. Do not rely on:

- container-local persistence for user files;
- `public/storage` or `storage:link` for private feedback attachments.

## Validate configuration

```bash
php artisan config:clear
php artisan finba:storage-check
```

The command writes, reads and deletes a temporary probe object under `health/`.

## Temporary URLs

When the disk supports Laravel temporary URLs, generate short-lived URLs on demand. Do not persist them.

If a temporary URL call fails against Supabase, keep the bucket private and continue serving feedback email attachments via server-side reads (`Attachment::fromStorageDisk`).

## Deferred

- automatic retention policies / scheduled orphan cleanup
- browser-direct uploads
- virus scanning / image transforms
- CDN configuration
