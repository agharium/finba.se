<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$dataPath = $root . '/resources/data/country-region-data.json';
$citiesDir = $root . '/resources/data/br-cities';

$data = json_decode(file_get_contents($dataPath), true, 512, JSON_THROW_ON_ERROR);

$byCode = [];

foreach (glob($citiesDir . '/*.json') as $file) {
    $code = basename($file, '.json');
    $byCode[$code] = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
}

$updated = [];

foreach ($data as &$country) {
    if (($country['countryShortCode'] ?? '') !== 'BR') {
        continue;
    }

    foreach ($country['regions'] as &$region) {
        $code = $region['shortCode'] ?? null;

        if ($code === null || ! isset($byCode[$code])) {
            continue;
        }

        $region['cities'] = $byCode[$code];
        $updated[] = $code;
    }

    unset($region);
}

unset($country);

sort($updated);

file_put_contents(
    $dataPath,
    json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
);

echo 'Updated states: ' . implode(', ', $updated) . PHP_EOL;
