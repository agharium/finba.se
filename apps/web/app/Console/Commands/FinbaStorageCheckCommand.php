<?php

namespace App\Console\Commands;

use App\Services\FileStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

class FinbaStorageCheckCommand extends Command
{
    protected $signature = 'finba:storage-check';

    protected $description = 'Validate the configured Finba.se storage disk with a write/read/delete probe';

    public function handle(FileStorageService $storage): int
    {
        $diskName = $storage->diskName();
        $path = 'health/storage-check-'.Str::uuid()->toString().'.txt';
        $payload = 'finba-storage-check:'.now()->toIso8601String();

        $this->info("Checking storage disk [{$diskName}]...");

        try {
            $written = $storage->disk()->put($path, $payload, ['visibility' => 'private']);

            if ($written !== true && $written !== $path) {
                throw new \RuntimeException('Write probe did not succeed.');
            }

            if (! $storage->exists($path)) {
                throw new \RuntimeException('Probe object was not found after write.');
            }

            $read = $storage->disk()->get($path);

            if ($read !== $payload) {
                throw new \RuntimeException('Probe object content did not match.');
            }

            if (! $storage->delete($path)) {
                throw new \RuntimeException('Probe object could not be deleted.');
            }

            if ($storage->exists($path)) {
                throw new \RuntimeException('Probe object still exists after delete.');
            }

            $this->info('Storage check passed.');
            $this->line("Disk: {$diskName}");
            $this->line('Probe path cleaned up successfully.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            try {
                $storage->delete($path);
            } catch (Throwable) {
                // ignore cleanup errors after a failed probe
            }

            $this->error('Storage check failed.');
            $this->line($exception->getMessage());

            return self::FAILURE;
        }
    }
}
