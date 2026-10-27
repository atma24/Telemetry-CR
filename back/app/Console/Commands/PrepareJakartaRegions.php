<?php

namespace App\Console\Commands;

use App\Enums\Wilayah\RegionLevel;
use App\Services\Wilayah\RegionGeoJsonDocument;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

#[Signature('app:prepare-jakarta-regions
    {--output=regions/jakarta : Directory on the local disk for regions.geojson and manifest.json}
    {--allow-partial : Allow source layers that differ from the verified DKI Jakarta counts}')]
#[Description('Fetch DKI Jakarta administrative boundaries from DPMPTSP and prepare a normalized GeoJSON import document')]
class PrepareJakartaRegions extends Command
{
    /** @var array<string, string> */
    private const SOURCES = [
        'city' => 'https://gis-dpmptsp.jakarta.go.id/arcgis/rest/services/Hosted/batas_kota_kab/FeatureServer/0',
        'district' => 'https://gis-dpmptsp.jakarta.go.id/arcgis/rest/services/Hosted/Batas_Administrasi_Kecamatan_Jakarta/FeatureServer/0',
        'village' => 'https://gis-dpmptsp.jakarta.go.id/arcgis/rest/services/Hosted/Batas_Administrasi_Kelurahan_DKI_Jakarta/FeatureServer/85',
    ];

    /** @var array<string, int> */
    private const EXPECTED_COUNTS = [
        'city' => 6,
        'district' => 44,
        'village' => 267,
    ];

    /** @var list<string> */
    private const CITY_CODES = ['3101', '3171', '3172', '3173', '3174', '3175'];

    private const PROVINCE_CODE = '31';

    private const PAGE_SIZE = 500;

    private const MAX_PAGES = 200;

    public function handle(): int
    {
        try {
            $cities = $this->cities($this->fetchFeatures(self::SOURCES['city']));
            $districts = $this->districts($this->fetchFeatures(self::SOURCES['district']), $cities['entries']);
            $villages = $this->villages($this->fetchFeatures(self::SOURCES['village']), $districts['entries']);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $counts = [
            'city' => count($cities['entries']),
            'district' => count($districts['entries']),
            'village' => count($villages['entries']),
        ];

        if (! $this->option('allow-partial') && $counts !== self::EXPECTED_COUNTS) {
            $this->error(sprintf(
                'Source counts do not match the verified DKI Jakarta boundaries (cities %d/%d, districts %d/%d, villages %d/%d). Re-run with --allow-partial only for testing.',
                $counts['city'], self::EXPECTED_COUNTS['city'],
                $counts['district'], self::EXPECTED_COUNTS['district'],
                $counts['village'], self::EXPECTED_COUNTS['village'],
            ));

            return self::FAILURE;
        }

        $excluded = array_merge($cities['excluded'], $districts['excluded'], $villages['excluded']);

        try {
            $features = array_map(
                fn (array $entry): array => $this->feature($entry),
                [
                    $this->provinceEntry($cities['entries']),
                    ...$cities['entries'],
                    ...$districts['entries'],
                    ...$villages['entries'],
                ],
            );
            $document = RegionGeoJsonDocument::fromFeatures($features);

            $output = trim((string) $this->option('output'), '/');
            $geoJsonPath = "{$output}/regions.geojson";
            $manifestPath = "{$output}/manifest.json";
            $document->write('local', $geoJsonPath);

            $manifest = [
                'prepared_at' => now()->toIso8601String(),
                'sources' => self::SOURCES,
                'expected_counts' => self::EXPECTED_COUNTS,
                'counts' => $document->countsByLevel(),
                'excluded' => array_values($excluded),
                'files' => [
                    'geojson' => $geoJsonPath,
                    'manifest' => $manifestPath,
                ],
            ];

            if (! Storage::disk('local')->put(
                $manifestPath,
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            )) {
                throw new RuntimeException("The region manifest could not be written to [local:{$manifestPath}].");
            }
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Level', 'Features'], collect($document->countsByLevel())
            ->map(fn (int $count, string $level): array => [$level, $count])
            ->values()
            ->all());

        if ($excluded !== []) {
            $this->warn(count($excluded).' source feature(s) were excluded as inconsistent with the boundary hierarchy:');

            $this->table(['Layer', 'Code', 'Reason'], array_map(
                fn (array $row): array => [$row['layer'], $row['code'], $row['reason']],
                array_values($excluded),
            ));
        }

        $this->info("Prepared {$geoJsonPath} and {$manifestPath} on the local disk.");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string|int|float|null>  $properties
     */
    private function text(array $properties, string $key): ?string
    {
        $value = $properties[$key] ?? null;

        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $geometry
     * @return array{code: string, name: string, level: string, parent_code: string|null, geometry: array<string, mixed>}
     */
    private function entry(string $code, string $name, string $level, ?string $parentCode, array $geometry): array
    {
        return [
            'code' => $code,
            'name' => $name,
            'level' => $level,
            'parent_code' => $parentCode,
            'geometry' => $geometry,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $features
     * @return array{entries: list<array{code: string, name: string, level: string, parent_code: string|null, geometry: array<string, mixed>}>, excluded: list<array{layer: string, code: string, reason: string}>}
     */
    private function cities(array $features): array
    {
        $entries = [];
        $excluded = [];

        foreach ($features as $feature) {
            $properties = $this->properties($feature);
            $code = $this->text($properties, 'kdpkab');
            $name = $this->text($properties, 'wadmkk');

            if ($code === null || $name === null) {
                throw new InvalidArgumentException('A city boundary is missing kdpkab or wadmkk.');
            }

            if (! in_array($code, self::CITY_CODES, true)) {
                throw new InvalidArgumentException("City boundary [{$code}] is outside DKI Jakarta.");
            }

            $level = $code === '3101' ? RegionLevel::Regency->value : RegionLevel::City->value;
            $entries[] = $this->entry($code, $name, $level, self::PROVINCE_CODE, $this->geometry($feature));
        }

        return ['entries' => $this->sorted($entries), 'excluded' => $excluded];
    }

    /**
     * @param  list<array<string, mixed>>  $features
     * @param  list<array{code: string, name: string, level: string, parent_code: string|null, geometry: array<string, mixed>}>  $cities
     * @return array{entries: list<array{code: string, name: string, level: string, parent_code: string|null, geometry: array<string, mixed>}>, excluded: list<array{layer: string, code: string, reason: string}>}
     */
    private function districts(array $features, array $cities): array
    {
        $cityCodes = array_column($cities, 'code');
        $entries = [];
        $excluded = [];

        foreach ($features as $feature) {
            $properties = $this->properties($feature);
            $code = $this->text($properties, 'kdcpum');
            $name = $this->text($properties, 'wadmkc');
            $cityCode = $this->text($properties, 'kdpkab');

            if ($code === null || $name === null) {
                throw new InvalidArgumentException('A district boundary is missing kdcpum or wadmkc.');
            }

            $parentCode = substr($code, 0, 4);

            if (
                strlen($code) !== 6
                || ! ctype_digit($code)
                || $cityCode !== $parentCode
                || ! in_array($parentCode, $cityCodes, true)
            ) {
                $excluded[] = [
                    'layer' => 'district',
                    'code' => $code,
                    'reason' => "Parent city [{$cityCode}] does not match district code [{$code}].",
                ];

                continue;
            }

            $entries[] = $this->entry($code, $name, RegionLevel::District->value, $parentCode, $this->geometry($feature));
        }

        return ['entries' => $this->sorted($entries), 'excluded' => $excluded];
    }

    /**
     * @param  list<array<string, mixed>>  $features
     * @param  list<array{code: string, name: string, level: string, parent_code: string|null, geometry: array<string, mixed>}>  $districts
     * @return array{entries: list<array{code: string, name: string, level: string, parent_code: string|null, geometry: array<string, mixed>}>, excluded: list<array{layer: string, code: string, reason: string}>}
     */
    private function villages(array $features, array $districts): array
    {
        $districtCodes = array_column($districts, 'code');
        $entries = [];

        foreach ($features as $feature) {
            $properties = $this->properties($feature);
            $code = $this->text($properties, 'kdepum');
            $name = $this->text($properties, 'wadmkd');

            if ($code === null || $name === null) {
                throw new InvalidArgumentException('A village boundary is missing kdepum or wadmkd.');
            }

            if (strlen($code) !== 10 || ! ctype_digit($code)) {
                throw new InvalidArgumentException("Village boundary code [{$code}] is not a ten-digit administrative code.");
            }

            $parentCode = substr($code, 0, 6);

            if (! in_array($parentCode, $districtCodes, true)) {
                throw new InvalidArgumentException("Village boundary [{$code}] references district [{$parentCode}] that is not in the prepared source.");
            }

            $entries[] = $this->entry($code, $name, RegionLevel::Village->value, $parentCode, $this->geometry($feature));
        }

        return ['entries' => $this->sorted($entries), 'excluded' => []];
    }

    /**
     * @param  array{code: string, name: string, level: string, parent_code: string|null, geometry: array<string, mixed>}  $entry
     * @return array<string, mixed>
     */
    private function feature(array $entry): array
    {
        return [
            'type' => 'Feature',
            'geometry' => $entry['geometry'],
            'properties' => [
                RegionGeoJsonDocument::PROPERTY_CODE => $entry['code'],
                RegionGeoJsonDocument::PROPERTY_NAME => $entry['name'],
                RegionGeoJsonDocument::PROPERTY_LEVEL => $entry['level'],
                RegionGeoJsonDocument::PROPERTY_PARENT => $entry['parent_code'],
            ],
        ];
    }

    /**
     * @param  list<array{code: string, name: string, level: string, parent_code: string|null, geometry: array<string, mixed>}>  $cities
     * @return array{code: string, name: string, level: string, parent_code: string|null, geometry: array<string, mixed>}
     */
    private function provinceEntry(array $cities): array
    {
        $polygons = [];

        foreach ($cities as $city) {
            if (($city['geometry']['type'] ?? null) === 'Polygon') {
                $polygons[] = $city['geometry']['coordinates'];

                continue;
            }

            foreach ($city['geometry']['coordinates'] ?? [] as $polygon) {
                $polygons[] = $polygon;
            }
        }

        if ($polygons === []) {
            throw new RuntimeException('No city polygons were available to compose the province boundary.');
        }

        return $this->entry(
            self::PROVINCE_CODE,
            'DKI JAKARTA',
            RegionLevel::Province->value,
            null,
            ['type' => 'MultiPolygon', 'coordinates' => $polygons],
        );
    }

    /**
     * @param  list<array{code: string, name: string, level: string, parent_code: string|null, geometry: array<string, mixed>}>  $entries
     * @return list<array{code: string, name: string, level: string, parent_code: string|null, geometry: array<string, mixed>}>
     */
    private function sorted(array $entries): array
    {
        usort($entries, fn (array $left, array $right): int => strcmp($left['code'], $right['code']));

        return $entries;
    }

    /**
     * @return array<string, string|int|float|null>
     */
    private function properties(mixed $feature): array
    {
        if (! is_array($feature) || ! is_array($feature['properties'] ?? null)) {
            throw new InvalidArgumentException('An ArcGIS feature is missing its properties object.');
        }

        /** @var array<string, string|int|float|null> $properties */
        $properties = $feature['properties'];

        return $properties;
    }

    /**
     * @return array<string, mixed>
     */
    private function geometry(mixed $feature): array
    {
        $geometry = is_array($feature) ? ($feature['geometry'] ?? null) : null;

        if (! is_array($geometry)) {
            throw new InvalidArgumentException('An ArcGIS feature is missing its geometry object.');
        }

        return $geometry;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchFeatures(string $serviceUrl): array
    {
        $features = [];
        $offset = 0;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $response = Http::timeout(120)
                ->retry(3, 1000)
                ->acceptJson()
                ->get("{$serviceUrl}/query", [
                    'where' => '1=1',
                    'outFields' => '*',
                    'outSR' => '4326',
                    'resultOffset' => $offset,
                    'resultRecordCount' => self::PAGE_SIZE,
                    'f' => 'geojson',
                ]);

            $response->throw();
            $payload = $response->json();

            if (! is_array($payload) || ($payload['type'] ?? null) !== 'FeatureCollection' || ! is_array($payload['features'] ?? null)) {
                throw new RuntimeException("The ArcGIS layer at [{$serviceUrl}] did not return a GeoJSON FeatureCollection.");
            }

            $batch = $payload['features'];
            $features = array_merge($features, $batch);
            $offset += count($batch);
            $exceeded = (bool) ($payload['properties']['exceededTransferLimit'] ?? false);

            if ($batch === [] || (count($batch) < self::PAGE_SIZE && ! $exceeded)) {
                break;
            }

            if ($page === self::MAX_PAGES - 1) {
                throw new RuntimeException("The ArcGIS layer at [{$serviceUrl}] exceeded the pagination safety limit.");
            }
        }

        if ($features === []) {
            throw new RuntimeException("The ArcGIS layer at [{$serviceUrl}] returned no features.");
        }

        return $features;
    }
}
