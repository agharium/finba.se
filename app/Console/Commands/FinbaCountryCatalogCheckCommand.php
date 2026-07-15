<?php

namespace App\Console\Commands;

use App\Models\Country;
use Illuminate\Console\Command;
use Throwable;

class FinbaCountryCatalogCheckCommand extends Command
{
    protected $signature = 'finba:country-catalog-check';

    protected $description = 'Validate the Finba.se country catalog JSON and Sushi SQLite bootstrap';

    public function handle(): int
    {
        $this->info('Checking country catalog...');

        try {
            Country::clearSushiSqliteCaches();
            Country::flushCatalogCache();

            $path = resource_path('data/country-region-data.json');
            $this->line('Source: '.$path);

            $rows = Country::loadCatalogRows();
            $count = Country::query()->count();
            $sample = Country::query()->where('code', 'BR')->first();

            if ($count < 1) {
                throw new \RuntimeException('Sushi countries table is empty after bootstrap.');
            }

            if ($sample === null || blank($sample->currency)) {
                throw new \RuntimeException('Expected Brazil (BR) with a currency after bootstrap.');
            }

            $this->info('Country catalog check passed.');
            $this->line('Rows from JSON: '.count($rows));
            $this->line('Rows via Sushi query: '.$count);
            $this->line('Sample code: '.$sample->code);
            $this->line('Caching: disabled (in-memory SQLite per process)');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Country catalog check failed.');
            $this->line($exception->getMessage());

            return self::FAILURE;
        }
    }
}
