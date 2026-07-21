# Storage

Finba.se stores private application files through Laravel's filesystem abstraction.

- Local development: `FINBA_STORAGE_DISK=local`
- Production: `FINBA_STORAGE_DISK=finba` (S3-compatible Supabase Storage)

Business code should not talk to provider SDKs directly. Use:

```php
Storage::disk(config('finba.storage.disk'))
```

## Why S3 protocol

Server-side uploads use Supabase Storage's S3-compatible API with Laravel's native S3 driver (`league/flysystem-aws-s3-v3`).

Do not:

- use the Supabase JavaScript client for server uploads;
- expose S3 credentials to the browser or Vite;
- reuse database or service-role keys as storage credentials;
- make the Finba.se bucket public.

## Dashboard setup

1. Open the Supabase project.
2. Create a private bucket named `finba` (recommended).
3. Open Storage → Configuration / S3.
4. Enable the S3 protocol if required.
5. Generate an S3 access key and secret for server use only.
6. Copy endpoint, region, access key ID, and secret access key.
7. Configure production environment variables.
8. Set `FINBA_STORAGE_DISK=finba`.

### Endpoint shape

Use exactly the endpoint shown by Supabase. Common forms:

```text
https://<project-ref>.storage.supabase.co/storage/v1/s3
https://<project-ref>.supabase.co/storage/v1/s3
```

Laravel disk `finba` sets `use_path_style_endpoint` to `true`.

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

- Treat S3 credentials as infrastructure secrets.
- Never commit them.
- Rotate immediately if exposed.
- Persist only object paths in the database, never signed or public URLs.

## Object paths

```text
feedback/{feedback_uuid}/{generated-filename}.webp
```

Future domains may include `avatars/`, `receipts/`, `imports/`, and `attachments/`.

Soft-deleted feedback keeps the object; force-delete removes it. Email attachments are read server-side from the configured disk.

## Stateless hosts

Container filesystems are disposable. Production uploads must use `FINBA_STORAGE_DISK=finba`. Do not rely on container-local persistence or `storage:link` for private feedback attachments.

## Validation

```bash
php artisan config:clear
php artisan finba:storage-check
```

The command writes, reads, and deletes a temporary probe object under `health/`.

## Temporary URLs

Generate short-lived URLs on demand when the disk supports them. Do not persist temporary URLs.

If temporary URL generation fails against Supabase, keep the bucket private and continue serving feedback attachments through server-side reads.

## Deferred work

- Automatic retention / orphan cleanup
- Browser-direct uploads
- Virus scanning / image transforms
- CDN configuration
