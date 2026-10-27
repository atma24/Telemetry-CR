<?php

namespace App\Services\Wilayah;

use App\Models\Wilayah\VersiWilayah;

class RegionMatcher
{
    /**
     * Source spellings that are unambiguous variants of one published name.
     * Keep this list short and evidence-based; do not add fuzzy matches.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        'GOLDOK' => 'GLODOK',
        'KREDANG' => 'KERENDANG',
        'PALMERIEM' => 'PALMERIAM',
        'TANAHSAREAL' => 'TANAHSEREAL',
    ];

    /** @var array<string, list<string>>|null */
    private ?array $districtsByKey = null;

    /** @var array<string, array<string, list<string>>>|null */
    private ?array $villagesByDistrict = null;

    public function match(?string $village, ?string $district): ?string
    {
        if (! $this->hasText($village) || ! $this->hasText($district)) {
            return null;
        }

        $this->loadPublishedRegions();

        $districtIds = $this->districtsByKey[$this->key($district)] ?? [];

        if (count($districtIds) !== 1) {
            return null;
        }

        $villageIds = $this->villagesByDistrict[$districtIds[0]][$this->key($village)] ?? [];

        return count($villageIds) === 1 ? $villageIds[0] : null;
    }

    /**
     * Normalize a source locality name without fuzzy matching.
     * Spacing, punctuation, case, and the "KEP." abbreviation are ignored.
     */
    public function key(?string $name): string
    {
        $name = mb_strtoupper(trim((string) $name));
        $name = (string) preg_replace('/\bKEP\.\s*/u', 'KEPULAUAN ', $name);
        $name = (string) preg_replace('/^(KELURAHAN|KECAMATAN|KEL\.|KEC\.)\s+/u', '', $name);
        $name = (string) preg_replace('/[^A-Z0-9]+/u', '', $name);

        return self::ALIASES[$name] ?? $name;
    }

    private function hasText(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }

    private function loadPublishedRegions(): void
    {
        if ($this->districtsByKey !== null) {
            return;
        }

        $this->districtsByKey = [];
        $this->villagesByDistrict = [];

        VersiWilayah::query()
            ->terpublikasi()
            ->whereIn('level', ['kecamatan', 'kelurahan'])
            ->get(['region_id', 'name', 'level', 'parent_region_id'])
            ->each(function (VersiWilayah $region): void {
                $key = $this->key($region->name);

                if ($key === '') {
                    return;
                }

                if ($region->level === 'kecamatan') {
                    $this->districtsByKey[$key][] = $region->region_id;

                    return;
                }

                if (is_string($region->parent_region_id)) {
                    $this->villagesByDistrict[$region->parent_region_id][$key][] = $region->region_id;
                }
            });
    }
}
