<?php

namespace App\Services\Wilayah;

use App\Jobs\Wilayah\ProcessRegionImport;
use App\Models\PekerjaanImpor;
use App\Models\Pengguna;
use App\Models\Wilayah\VersiWilayah;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class RegionImportService
{
    public function __construct(
        private readonly WilayahService $wilayahService,
        private readonly RegionGeometry $regionGeometry,
    ) {}

    public function queueImport(UploadedFile $file, Pengguna $actor, ?string $sourceId): PekerjaanImpor
    {
        $path = $file->store('imports/regions/source', 'local');
        if ($path === false) {
            throw new InvalidArgumentException('The uploaded region file could not be stored securely.');
        }

        try {
            $job = PekerjaanImpor::query()->create([
                'target_type' => 'region_geojson', 'source_id' => $sourceId,
                'original_filename' => basename(Str::limit($file->getClientOriginalName(), 255, '')),
                'file_path' => $path, 'status' => 'uploaded', 'processed_rows' => 0,
                'initiated_by' => $actor->id,
            ]);
            ProcessRegionImport::dispatch($job->id);

            return $job;
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }
    }

    public function process(string $jobId): void
    {
        $job = PekerjaanImpor::query()->where('target_type', 'region_geojson')->findOrFail($jobId);
        if (in_array($job->status, ['completed', 'completed_with_errors', 'failed'], true)) {
            return;
        }
        $actor = $job->pembuat;
        $path = Storage::disk('local')->path($job->file_path ?? '');
        if ($actor === null || ! is_file($path)) {
            $this->markFailed($job->id, new InvalidArgumentException('The importing Admin or stored GeoJSON is unavailable.'));

            return;
        }

        $job->forceFill(['status' => 'processing', 'processed_rows' => 0, 'accepted_rows' => 0, 'rejected_rows' => 0, 'warning_rows' => 0])->save();

        try {
            $document = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($document) || ($document['type'] ?? null) !== 'FeatureCollection' || ! is_array($document['features'] ?? null)) {
                throw new InvalidArgumentException('Region upload must be a GeoJSON FeatureCollection.');
            }
            $job->forceFill(['total_rows' => count($document['features'])])->save();
            $report = tmpfile();
            if ($report === false) {
                throw new InvalidArgumentException('Unable to create the validation report.');
            }
            fputcsv($report, ['row_number', 'field', 'reason', 'source_value']);
            $total = $accepted = $rejected = $warnings = 0;
            foreach ($document['features'] as $index => $feature) {
                $total++;
                try {
                    $properties = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
                    $code = $this->property($properties, ['kode_wilayah', 'kode', 'code', 'region_code', 'KODE_WIL', 'KDEPUM', 'kd wilayah']);
                    $name = $this->property($properties, ['nama_wilayah', 'nama', 'name', 'region_name', 'WADMKD', 'WADMKC', 'WADMKK', 'WADMPR', 'NAMOBJ']);
                    $levelRaw = $this->property($properties, ['tingkat_wilayah', 'tingkat', 'level', 'region_level', 'admin_level', 'WADM_LEVEL']);
                    $level = $this->normalizeLevel($levelRaw);
                    $parentCode = $this->property($properties, ['kode_wilayah_induk', 'kode_induk', 'parent_code', 'parent_region_code', 'KODE_INDUK']);
                    $geometry = $feature['geometry'] ?? null;
                    if ($code === null || $name === null || $level === null || ! $this->regionGeometry->isValid($geometry)) {
                        throw new InvalidArgumentException('Feature needs recognized region code, name, level, and valid GeoJSON geometry properties.');
                    }
                    if (VersiWilayah::query()->where('code', $code)->exists()) {
                        throw new InvalidArgumentException('A region with this code already exists; existing history was retained.');
                    }
                    $parentId = null;
                    if ($parentCode !== null) {
                        $parentId = VersiWilayah::query()->terpublikasi()->where('code', $parentCode)->value('region_id');
                        if (! is_string($parentId)) {
                            throw new InvalidArgumentException('Parent region code has no currently published match. Import/publish the parent first.');
                        }
                    }
                    $centroidLatitude = $this->numeric($this->property($properties, ['centroid_latitude', 'latitude_centroid']), -90, 90);
                    $centroidLongitude = $this->numeric($this->property($properties, ['centroid_longitude', 'longitude_centroid']), -180, 180);
                    $attributes = [
                        'code' => $code, 'name' => $name, 'level' => $level,
                        'parent_region_id' => $parentId, 'source_id' => $job->source_id,
                        'import_job_id' => $job->id, 'source_original_data' => $feature,
                        'geometry' => $geometry,
                        'centroid_latitude' => $centroidLatitude,
                        'centroid_longitude' => $centroidLongitude,
                    ];
                    DB::transaction(fn () => $this->wilayahService->createDraft($attributes, $actor));
                    $accepted++;
                    if (! isset($attributes['centroid_latitude']) || ! isset($attributes['centroid_longitude'])) {
                        $warnings++;
                        fputcsv($report, [$index + 1, 'centroid', 'Centroid not present in source; left null.', '']);
                    }
                } catch (Throwable $exception) {
                    $rejected++;
                    fputcsv($report, [$index + 1, 'feature', $exception->getMessage(), '']);
                }
                $job->forceFill([
                    'status' => 'processing', 'total_rows' => count($document['features']), 'processed_rows' => $total,
                    'accepted_rows' => $accepted, 'rejected_rows' => $rejected, 'warning_rows' => $warnings,
                ])->save();
            }
            $reportPath = null;
            if ($rejected + $warnings > 0) {
                $reportPath = "imports/regions/errors/{$job->id}.csv";
                rewind($report);
                Storage::disk('local')->writeStream($reportPath, $report);
            }
            fclose($report);
            $job->forceFill([
                'status' => $rejected > 0 ? 'completed_with_errors' : 'completed',
                'total_rows' => $total, 'processed_rows' => $total, 'accepted_rows' => $accepted,
                'rejected_rows' => $rejected, 'warning_rows' => $warnings, 'error_report_path' => $reportPath,
                'finished_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $this->markFailed($job->id, $exception);
        }
    }

    public function markFailed(string $jobId, ?Throwable $exception): void
    {
        PekerjaanImpor::query()->where('target_type', 'region_geojson')->whereKey($jobId)->update([
            'status' => 'failed', 'failure_reason' => Str::limit($exception?->getMessage() ?? 'Region import failed.', 1000), 'finished_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $properties
     * @param  array<int, string>  $aliases
     */
    private function property(array $properties, array $aliases): ?string
    {
        $normalized = [];
        foreach ($properties as $key => $value) {
            $normalized[Str::snake(mb_strtolower((string) $key))] = $value;
        }
        foreach ($aliases as $alias) {
            $value = $normalized[Str::snake(mb_strtolower($alias))] ?? null;
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    private function normalizeLevel(?string $level): ?string
    {
        if ($level === null) {
            return null;
        }
        $level = mb_strtolower(trim($level));
        $aliases = ['province' => 'provinsi', 'city' => 'kota', 'district' => 'kecamatan', 'village' => 'kelurahan', 'kabupaten/kota' => 'kota'];
        $level = $aliases[$level] ?? $level;

        return in_array($level, ['provinsi', 'kota', 'kabupaten', 'kecamatan', 'kelurahan'], true) ? $level : null;
    }

    private function numeric(?string $value, float $minimum, float $maximum): ?float
    {
        if ($value === null) {
            return null;
        }
        if (! is_numeric($value) || (float) $value < $minimum || (float) $value > $maximum) {
            throw new InvalidArgumentException('Centroid coordinate is outside the valid geographic range.');
        }
        $numeric = (float) $value;

        return $numeric;
    }
}
