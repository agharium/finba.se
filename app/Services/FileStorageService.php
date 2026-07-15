<?php

namespace App\Services;

use DateTimeInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class FileStorageService
{
    public function diskName(): string
    {
        return (string) config('finba.storage.disk', 'local');
    }

    public function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    public function storePrivateUpload(UploadedFile $file, string $directory, ?string $filename = null): string
    {
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin'));
        $extension = preg_replace('/[^a-z0-9]+/', '', $extension) ?: 'bin';
        $filename ??= Str::uuid()->toString().'.'.$extension;
        $directory = trim($directory, '/');

        $path = $this->disk()->putFileAs($directory, $file, $filename, [
            'visibility' => 'private',
        ]);

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Failed to store private upload.');
        }

        return $path;
    }

    public function exists(?string $path): bool
    {
        return filled($path) && $this->disk()->exists($path);
    }

    public function delete(?string $path): bool
    {
        if (! filled($path)) {
            return false;
        }

        return (bool) $this->disk()->delete($path);
    }

    public function mimeType(string $path): ?string
    {
        return $this->disk()->mimeType($path) ?: null;
    }

    public function temporaryUrl(string $path, DateTimeInterface $expiration): string
    {
        return $this->disk()->temporaryUrl($path, $expiration);
    }

    public function supportsTemporaryUrls(): bool
    {
        $disk = $this->disk();

        return method_exists($disk, 'providesTemporaryUrls')
            && $disk->providesTemporaryUrls();
    }
}
