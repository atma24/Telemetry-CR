<?php

namespace App\Services\Wilayah;

use App\Enums\Wilayah\RegionLevel;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

class RegionGeoJsonDocument
{
    public const PROPERTY_CODE = 'kode_wilayah';

    public const PROPERTY_NAME = 'nama_wilayah';

    public const PROPERTY_LEVEL = 'tingkat_wilayah';

    public const PROPERTY_PARENT = 'kode_wilayah_induk';

    /**
     * @param  list<array{code: string, name: string, level: string, parent_code: string|null, geometry: array<string, mixed>}>  $entries
     */
    private function __construct(
        private readonly array $entries,
        private readonly RegionGeometry $regionGeometry,
    ) {}

    /**
     * @param  array<int, mixed>  $features
     */
    public static function fromFeatures(array $features): self
    {
        $entries = [];
        $seen = [];
        $geometry = new RegionGeometry;

        foreach ($features as $index => $feature) {
            $entry = self::normalizeFeature($feature, $index + 1, $geometry);

            if (isset($seen[$entry['code']])) {
                throw new InvalidArgumentException("Region code [{$entry['code']}] appears more than once in the prepared document.");
            }

            $seen[$entry['code']] = true;
            $entries[] = $entry;
        }

        if ($entries === []) {
            throw new InvalidArgumentException('The region document contains no features.');
        }

        $document = new self($entries, $geometry);
        $document->assertHierarchy();

        return $document;
    }

    public static function read(string $path): self
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("Prepared region document [{$path}] does not exist.");
        }

        try {
            $document = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Prepared region document contains invalid JSON.', previous: $exception);
        }

        if (! is_array($document) || ($document['type'] ?? null) !== 'FeatureCollection' || ! is_array($document['features'] ?? null)) {
            throw new InvalidArgumentException('Prepared region document must be a GeoJSON FeatureCollection.');
        }

        return self::fromFeatures($document['features']);
    }

    /**
     * @return list<array{code: string, name: string, level: string, parent_code: string|null, geometry: array<string, mixed>}>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * @return array<string, list<array{code: string, name: string, level: string, parent_code: string|null, geometry: array<string, mixed>}>>
     */
    public function entriesByLevel(): array
    {
        $grouped = [];

        foreach ($this->orderedLevels() as $level) {
            $grouped[$level] = array_values(array_filter(
                $this->entries,
                fn (array $entry): bool => $entry['level'] === $level,
            ));
        }

        return $grouped;
    }

    /** @return list<string> */
    public function orderedLevels(): array
    {
        return [
            RegionLevel::Province->value,
            RegionLevel::City->value,
            RegionLevel::Regency->value,
            RegionLevel::District->value,
            RegionLevel::Village->value,
        ];
    }

    /** @return array<string, int> */
    public function countsByLevel(): array
    {
        $counts = array_fill_keys($this->orderedLevels(), 0);

        foreach ($this->entries as $entry) {
            $counts[$entry['level']]++;
        }

        return $counts;
    }

    /** @return array<string, mixed> */
    public function toFeatureCollection(): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => array_map(fn (array $entry): array => [
                'type' => 'Feature',
                'geometry' => $entry['geometry'],
                'properties' => [
                    self::PROPERTY_CODE => $entry['code'],
                    self::PROPERTY_NAME => $entry['name'],
                    self::PROPERTY_LEVEL => $entry['level'],
                    self::PROPERTY_PARENT => $entry['parent_code'],
                ],
            ], $this->entries),
        ];
    }

    public function write(string $disk, string $path): void
    {
        $contents = json_encode(
            $this->toFeatureCollection(),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        if (! Storage::disk($disk)->put($path, $contents)) {
            throw new RuntimeException("The prepared region document could not be written to [{$disk}:{$path}].");
        }
    }

    /**
     * @return array{code: string, name: string, level: string, parent_code: string|null, geometry: array<string, mixed>}
     */
    private static function normalizeFeature(mixed $feature, int $number, RegionGeometry $regionGeometry): array
    {
        if (! is_array($feature) || ! is_array($feature['properties'] ?? null) || ! is_array($feature['geometry'] ?? null)) {
            throw new InvalidArgumentException("Region feature {$number} must include properties and geometry objects.");
        }

        $properties = $feature['properties'];
        $code = self::text($properties[self::PROPERTY_CODE] ?? null);
        $name = self::text($properties[self::PROPERTY_NAME] ?? null);
        $level = self::text($properties[self::PROPERTY_LEVEL] ?? null);
        $parentCode = self::text($properties[self::PROPERTY_PARENT] ?? null);

        if ($code === null || $name === null || $level === null) {
            throw new InvalidArgumentException("Region feature {$number} needs a code, name, and level.");
        }

        if (RegionLevel::tryFrom($level) === null) {
            throw new InvalidArgumentException("Region feature {$number} has an unrecognized level [{$level}].");
        }

        if ($level === RegionLevel::Province->value) {
            $parentCode = null;
        } elseif ($parentCode === null) {
            throw new InvalidArgumentException("Region feature {$number} [{$code}] needs a parent code.");
        }

        if (! $regionGeometry->isValid($feature['geometry'])) {
            throw new InvalidArgumentException("Region feature {$number} [{$code}] has invalid boundary geometry.");
        }

        return [
            'code' => $code,
            'name' => $name,
            'level' => $level,
            'parent_code' => $parentCode,
            'geometry' => $feature['geometry'],
        ];
    }

    private function assertHierarchy(): void
    {
        $byCode = [];

        foreach ($this->entries as $entry) {
            $byCode[$entry['code']] = $entry;
        }

        $allowedParents = [
            RegionLevel::Province->value => [],
            RegionLevel::City->value => [RegionLevel::Province->value],
            RegionLevel::Regency->value => [RegionLevel::Province->value],
            RegionLevel::District->value => [RegionLevel::City->value, RegionLevel::Regency->value],
            RegionLevel::Village->value => [RegionLevel::District->value],
        ];

        foreach ($this->entries as $entry) {
            if ($entry['parent_code'] === null) {
                continue;
            }

            $parent = $byCode[$entry['parent_code']] ?? null;

            if ($parent === null) {
                throw new InvalidArgumentException("Region [{$entry['code']}] references missing parent [{$entry['parent_code']}].");
            }

            if (! in_array($parent['level'], $allowedParents[$entry['level']], true)) {
                throw new InvalidArgumentException("Region [{$entry['code']}] has parent [{$parent['code']}] with an invalid level.");
            }
        }
    }

    private static function text(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
