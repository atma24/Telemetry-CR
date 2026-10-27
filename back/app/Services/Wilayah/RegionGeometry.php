<?php

namespace App\Services\Wilayah;

use InvalidArgumentException;

class RegionGeometry
{
    public function isValid(mixed $geometry): bool
    {
        if (! is_array($geometry) || ! in_array($geometry['type'] ?? null, ['Polygon', 'MultiPolygon'], true)) {
            return false;
        }

        $coordinates = $geometry['coordinates'] ?? null;
        if (! is_array($coordinates) || $coordinates === []) {
            return false;
        }

        return match ($geometry['type']) {
            'Polygon' => $this->validPolygon($coordinates),
            'MultiPolygon' => collect($coordinates)->isNotEmpty()
                && collect($coordinates)->every(fn (mixed $polygon): bool => is_array($polygon) && $this->validPolygon($polygon)),
            default => false,
        };
    }

    /** @param array<string, mixed> $geometry */
    public function ensureValid(mixed $geometry): void
    {
        if (! $this->isValid($geometry)) {
            throw new InvalidArgumentException('Region geometry must be a valid Polygon or MultiPolygon with closed rings.');
        }
    }

    /** @param array<int, mixed> $position */
    private function validPosition(array $position): bool
    {
        return count($position) >= 2
            && is_numeric($position[0] ?? null)
            && is_numeric($position[1] ?? null)
            && (float) $position[0] >= -180
            && (float) $position[0] <= 180
            && (float) $position[1] >= -90
            && (float) $position[1] <= 90;
    }

    /** @param array<int, mixed> $line */
    private function validLine(array $line): bool
    {
        return count($line) >= 2
            && collect($line)->every(fn (mixed $position): bool => is_array($position) && $this->validPosition($position));
    }

    /** @param array<int, mixed> $rings */
    private function validPolygon(array $rings): bool
    {
        if ($rings === []) {
            return false;
        }

        foreach ($rings as $ring) {
            if (! is_array($ring) || count($ring) < 4 || ! $this->validLine($ring) || $ring[0] !== $ring[array_key_last($ring)]) {
                return false;
            }
        }

        return true;
    }
}
