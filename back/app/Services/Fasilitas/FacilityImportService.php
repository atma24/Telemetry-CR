<?php

namespace App\Services\Fasilitas;

use App\Enums\Wilayah\PublicationStatus;
use App\Jobs\Fasilitas\ProcessFacilityImport;
use App\Models\Fasilitas\Fasilitas;
use App\Models\Fasilitas\VersiFasilitas;
use App\Models\PekerjaanImpor;
use App\Models\Pengguna;
use App\Services\Apbd\ApbdSpreadsheetReader;
use App\Services\Audit\AuditLogService;
use App\Services\Wilayah\RegionMatcher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class FacilityImportService
{
    private const BATCH_SIZE = 250;

    public function __construct(
        private readonly ApbdSpreadsheetReader $reader,
        private readonly AuditLogService $auditLogService,
        private readonly RegionMatcher $regionMatcher,
    ) {}

    public function queueImport(UploadedFile $file, string $datasetType, Pengguna $actor, ?string $sourceId, ?string $period): PekerjaanImpor
    {
        if ($datasetType === 'osm' && ! in_array(strtolower($file->getClientOriginalExtension()), ['geojson', 'json'], true)) {
            throw ValidationException::withMessages(['file' => 'OSM facilities must be uploaded as GeoJSON.']);
        }

        if ($datasetType !== 'osm' && in_array(strtolower($file->getClientOriginalExtension()), ['geojson', 'json'], true)) {
            throw ValidationException::withMessages(['file' => 'School and market data must use CSV or spreadsheet format.']);
        }

        $path = $file->store('imports/facilities/source', 'local');

        if ($path === false) {
            throw ValidationException::withMessages(['file' => 'The uploaded file could not be stored securely.']);
        }

        try {
            $job = PekerjaanImpor::query()->create([
                'target_type' => 'facility_'.$datasetType,
                'source_id' => $sourceId,
                'original_filename' => basename(Str::limit(str_replace('\\', '/', $file->getClientOriginalName()), 255, '')),
                'file_path' => $path,
                'status' => 'uploaded',
                'processed_rows' => 0,
                'options' => ['periode_data' => $period],
                'initiated_by' => $actor->id,
            ]);

            ProcessFacilityImport::dispatch($job->id);

            return $job;
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }
    }

    public function process(string $importJobId): void
    {
        $job = PekerjaanImpor::query()->whereIn('target_type', ['facility_school', 'facility_market', 'facility_osm'])->findOrFail($importJobId);

        if (in_array($job->status, ['completed', 'completed_with_errors', 'failed'], true)) {
            return;
        }

        $actor = $job->pembuat;

        if ($actor === null || $job->file_path === null) {
            $this->markFailed($job->id, new InvalidArgumentException('The importing Admin or stored source file is unavailable.'));

            return;
        }

        $job->forceFill([
            'status' => 'processing', 'processed_rows' => 0, 'accepted_rows' => 0,
            'rejected_rows' => 0, 'warning_rows' => 0, 'total_rows' => null,
            'error_report_path' => null, 'failure_reason' => null,
        ])->save();

        $report = tmpfile();

        if ($report === false) {
            $this->markFailed($job->id, new RuntimeException('Unable to create an import report.'));

            return;
        }

        try {
            fputcsv($report, ['row_number', 'field', 'reason', 'source_value']);
            if ($job->target_type === 'facility_osm') {
                $counts = $this->processGeoJson($job, $actor, $report);
            } else {
                $counts = $this->processTable($job, $actor, $report);
            }

            rewind($report);
            $reportPath = null;
            if ($counts['rejected'] > 0 || $counts['warnings'] > 0) {
                $reportPath = "imports/facilities/errors/{$job->id}.csv";
                if (! Storage::disk('local')->writeStream($reportPath, $report)) {
                    throw new RuntimeException('The import report could not be saved securely.');
                }
            }

            $status = $counts['rejected'] > 0 ? 'completed_with_errors' : 'completed';
            $job->forceFill([
                'status' => $status, 'total_rows' => $counts['total'], 'processed_rows' => $counts['total'],
                'accepted_rows' => $counts['accepted'], 'rejected_rows' => $counts['rejected'],
                'warning_rows' => $counts['warnings'], 'error_report_path' => $reportPath,
                'finished_at' => now(),
            ])->save();
            $this->auditLogService->record($actor, 'facility.imported', 'facility_import', $job->id, null, $counts);
        } catch (Throwable $exception) {
            $this->markFailed($job->id, $exception);
        } finally {
            fclose($report);
        }
    }

    /** @return array{total: int, accepted: int, rejected: int, warnings: int} */
    private function processTable(PekerjaanImpor $job, Pengguna $actor, mixed $report): array
    {
        $file = $this->storedFile($job);
        $kind = $job->target_type === 'facility_school' ? 'school' : 'market';
        $required = $kind === 'school' ? ['nama_sekolah'] : ['periode_data', 'nama_pasar'];
        $allowed = $kind === 'school'
            ? ['periode_data', 'npsn', 'nama_sekolah', 'alamat', 'kelurahan', 'kecamatan', 'jumlah_siswa', 'jumlah_guru', 'kepala_sekolah', 'telp_sekolah', 'akreditasi', 'latitude', 'longitude']
            : ['periode_data', 'wilayah', 'kecamatan', 'kelurahan', 'nama_pasar', 'alamat', 'kode_pos', 'telepon', 'koordinat_x', 'koordinat_y'];
        if ($kind === 'school' && ($job->options['periode_data'] ?? null) === null) {
            $required[] = 'periode_data';
        }

        $totalRows = 0;
        foreach ($this->reader->rows($file, $required, $allowed) as $_row) {
            $totalRows++;
        }
        $job->forceFill(['total_rows' => $totalRows])->save();

        $total = $accepted = $rejected = $warnings = 0;
        foreach ($this->reader->rows($file, $required, $allowed) as $sourceRow) {
            $total++;
            try {
                $values = $sourceRow['values'];
                $period = $values['periode_data'] ?? $job->options['periode_data'] ?? null;
                if (! is_string($period) || trim($period) === '') {
                    throw new InvalidArgumentException('A data period is required in the file or upload form.');
                }
                $name = trim($values[$kind === 'school' ? 'nama_sekolah' : 'nama_pasar'] ?? '');
                if ($name === '') {
                    throw new InvalidArgumentException('A facility name is required.');
                }
                $lat = $kind === 'market' ? ($values['koordinat_x'] ?? null) : ($values['latitude'] ?? null);
                $lon = $kind === 'market' ? ($values['koordinat_y'] ?? null) : ($values['longitude'] ?? null);
                [$lat, $lon] = $this->coordinates($lat, $lon);
                $identifier = $kind === 'school' && trim($values['npsn'] ?? '') !== ''
                    ? 'school:'.trim($values['npsn'])
                    : $this->fallbackKey($kind, $period, $values, $name);
                $matchedRegion = $this->matchRegion($values['kelurahan'] ?? null, $values['kecamatan'] ?? null);
                $wasDuplicate = DB::transaction(function () use ($identifier, $kind, $period, $values, $name, $lat, $lon, $matchedRegion, $job, $actor): bool {
                    $facility = Fasilitas::query()->where('external_key', $identifier)->lockForUpdate()->first();
                    $versionNo = 1;
                    if ($facility !== null) {
                        $existingPeriod = $kind === 'school'
                            ? VersiFasilitas::query()->where('facility_id', $facility->id)->whereHas('sekolah', fn ($query) => $query->where('periode_data', $period))->exists()
                            : true;
                        if ($existingPeriod) {
                            return true;
                        }
                        $versionNo = (int) $facility->versi()->max('version_no') + 1;
                    }

                    $categoryId = DB::table('categories')->where('slug', $kind === 'school' ? 'sekolah' : 'pasar')->value('id');
                    if (! is_string($categoryId)) {
                        throw new RuntimeException('Required facility category seed is missing.');
                    }
                    $facility ??= Fasilitas::query()->create(['external_key' => $identifier]);
                    $version = $facility->versi()->create([
                        'version_no' => $versionNo,
                        'name' => $name,
                        'category_id' => $categoryId,
                        'region_id' => $matchedRegion,
                        'address' => $this->nullableValue($values['alamat'] ?? null),
                        'latitude' => $lat,
                        'longitude' => $lon,
                        'source_id' => $job->source_id,
                        'import_job_id' => $job->id,
                        'source_original_data' => $values,
                        'publication_status' => PublicationStatus::Draft,
                        'created_by' => $actor->id,
                    ]);
                    if ($kind === 'school') {
                        $version->sekolah()->create([
                            'periode_data' => $period, 'npsn' => $this->nullableValue($values['npsn'] ?? null),
                            'kelurahan_sumber' => $this->nullableValue($values['kelurahan'] ?? null),
                            'kecamatan_sumber' => $this->nullableValue($values['kecamatan'] ?? null),
                            'jumlah_siswa' => $this->nullableInteger($values['jumlah_siswa'] ?? null),
                            'jumlah_guru' => $this->nullableInteger($values['jumlah_guru'] ?? null),
                            'kepala_sekolah' => $this->nullableValue($values['kepala_sekolah'] ?? null),
                            'telp_sekolah' => $this->nullableValue($values['telp_sekolah'] ?? null),
                            'akreditasi' => $this->nullableValue($values['akreditasi'] ?? null),
                        ]);
                    } else {
                        $version->lokasiPasar()->create([
                            'periode_data' => $period,
                            'wilayah_sumber' => $this->nullableValue($values['wilayah'] ?? null),
                            'kecamatan_sumber' => $this->nullableValue($values['kecamatan'] ?? null),
                            'kelurahan_sumber' => $this->nullableValue($values['kelurahan'] ?? null),
                            'kode_pos' => $this->nullableValue($values['kode_pos'] ?? null),
                            'telepon' => $this->nullableValue($values['telepon'] ?? null),
                        ]);
                    }

                    return false;
                });
                if ($wasDuplicate) {
                    $rejected++;
                    $this->reportRow($report, $sourceRow['row_number'], 'duplicate', 'Existing facility identity found; no row was overwritten.', $name);

                    continue;
                }

                $accepted++;
                if ($lat === null || $matchedRegion === null) {
                    $warnings++;
                    $this->reportRow($report, $sourceRow['row_number'], 'location', $lat === null ? 'Coordinates are unavailable.' : 'Source locality was not matched to a published region.', '');
                }
            } catch (Throwable $exception) {
                $rejected++;
                $this->reportRow($report, $sourceRow['row_number'], 'row', $exception->getMessage(), json_encode($sourceRow['source_original_data'], JSON_UNESCAPED_UNICODE) ?: '');
            }
            $this->saveProgress($job, $total, $accepted, $rejected, $warnings);
        }

        return compact('total', 'accepted', 'rejected', 'warnings');
    }

    /** @return array{total: int, accepted: int, rejected: int, warnings: int} */
    private function processGeoJson(PekerjaanImpor $job, Pengguna $actor, mixed $report): array
    {
        $path = Storage::disk('local')->path($job->file_path ?? '');
        try {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('OSM import file contains invalid JSON.', previous: $exception);
        }
        if (! is_array($data) || ($data['type'] ?? null) !== 'FeatureCollection' || ! is_array($data['features'] ?? null)) {
            throw new InvalidArgumentException('OSM import must be a GeoJSON FeatureCollection with a features array.');
        }

        $total = $accepted = $rejected = $warnings = 0;
        $job->forceFill(['total_rows' => count($data['features'])])->save();
        foreach ($data['features'] as $index => $feature) {
            $total++;
            try {
                if (! is_array($feature) || ! is_array($feature['properties'] ?? null) || ! is_array($feature['geometry'] ?? null)) {
                    throw new InvalidArgumentException('Feature must include properties and geometry objects.');
                }
                $properties = $feature['properties'];
                $osmId = $this->property($properties, ['@id', 'id', 'osm_id']);
                if ($osmId === null && is_scalar($feature['id'] ?? null) && trim((string) $feature['id']) !== '') {
                    $osmId = trim((string) $feature['id']);
                }
                $identity = $osmId !== null ? 'osm:'.$osmId : 'osm:feature:'.hash('sha256', json_encode($feature, JSON_THROW_ON_ERROR));
                $name = $this->property($properties, ['name']);
                if ($name === null) {
                    throw new InvalidArgumentException('The OSM feature has no non-empty name property.');
                }
                [$lat, $lon] = $this->geoJsonCoordinates($feature['geometry']);
                if ($lat === null || $lon === null) {
                    throw new InvalidArgumentException('OSM facility geometry must be a Point with valid longitude/latitude coordinates.');
                }
                $existing = Fasilitas::query()->where('external_key', $identity)->exists();
                if ($existing) {
                    $rejected++;
                    $this->reportRow($report, $index + 1, 'duplicate', 'Existing OSM identity found; no row was overwritten.', $identity);

                    continue;
                }
                DB::transaction(function () use ($properties, $feature, $identity, $name, $lat, $lon, $job, $actor): void {
                    $category = $this->property($properties, ['amenity', 'shop', 'leisure', 'healthcare', 'office']);
                    $categorySlug = in_array(mb_strtolower($category ?? ''), ['school'], true)
                        ? 'sekolah'
                        : (in_array(mb_strtolower($category ?? ''), ['marketplace', 'market'], true) ? 'pasar' : Str::slug($category ?? 'fasilitas-publik'));
                    $categoryId = DB::table('categories')->where('slug', $categorySlug)->value('id')
                        ?? DB::table('categories')->where('slug', 'fasilitas-publik')->value('id');
                    if (! is_string($categoryId)) {
                        throw new RuntimeException('No matching facility category or fallback category exists.');
                    }
                    $addressParts = array_filter([
                        $this->property($properties, ['address', 'addr:full', 'addr:street', 'street']),
                        $this->property($properties, ['addr:housenumber', 'housenumber']),
                        $this->property($properties, ['addr:suburb', 'suburb']),
                    ]);
                    $tags = $properties;
                    $accessibility = [];
                    foreach (['wheelchair', 'ramp', 'toilets:wheelchair', 'tactile_paving'] as $key) {
                        $accessibility[$key] = $properties[$key] ?? null;
                    }
                    $tags['accessibility'] = $accessibility;
                    $facility = Fasilitas::query()->create(['external_key' => $identity]);
                    $facility->versi()->create([
                        'version_no' => 1, 'name' => $name, 'category_id' => $categoryId,
                        'region_id' => null, 'address' => $addressParts === [] ? null : implode(', ', $addressParts),
                        'latitude' => $lat, 'longitude' => $lon, 'attributes' => $tags,
                        'source_id' => $job->source_id, 'import_job_id' => $job->id,
                        'source_original_data' => $feature, 'publication_status' => PublicationStatus::Draft,
                        'created_by' => $actor->id,
                    ]);
                });
                $accepted++;
            } catch (Throwable $exception) {
                $rejected++;
                $this->reportRow($report, $index + 1, 'feature', $exception->getMessage(), '');
            }
            $this->saveProgress($job, $total, $accepted, $rejected, $warnings);
        }

        return compact('total', 'accepted', 'rejected', 'warnings');
    }

    /** @return array{float|null, float|null} */
    private function coordinates(?string $latitude, ?string $longitude): array
    {
        $latitude = $this->nullableValue($latitude);
        $longitude = $this->nullableValue($longitude);
        if ($latitude === null || $longitude === null) {
            return [null, null];
        }
        if (! is_numeric($latitude) || ! is_numeric($longitude) || (float) $latitude < -90 || (float) $latitude > 90 || (float) $longitude < -180 || (float) $longitude > 180) {
            throw new InvalidArgumentException('Coordinates are outside valid latitude/longitude ranges.');
        }

        return [(float) $latitude, (float) $longitude];
    }

    /** @param array<string, mixed> $geometry
     * @return array{float|null, float|null}
     */
    private function geoJsonCoordinates(array $geometry): array
    {
        $coordinates = $geometry['coordinates'] ?? null;
        if (($geometry['type'] ?? null) !== 'Point' || ! is_array($coordinates) || count($coordinates) < 2
            || ! is_numeric($coordinates[0] ?? null) || ! is_numeric($coordinates[1] ?? null)) {
            throw new InvalidArgumentException('OSM facility geometry must be a Point with numeric coordinates.');
        }

        return $this->coordinates(is_numeric($coordinates[1]) ? (string) $coordinates[1] : null, is_numeric($coordinates[0]) ? (string) $coordinates[0] : null);
    }

    /** @param array<string, mixed> $properties
     * @param  array<int, string>  $aliases
     */
    private function property(array $properties, array $aliases): ?string
    {
        foreach ($aliases as $alias) {
            $value = $properties[$alias] ?? null;
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    private function matchRegion(?string $village, ?string $district): ?string
    {
        return $this->regionMatcher->match($village, $district);
    }

    /** @param array<string, string> $values */
    private function fallbackKey(string $kind, string $period, array $values, string $name): string
    {
        $identity = implode('|', [$period, mb_strtolower($values['wilayah'] ?? ''), mb_strtolower($values['alamat'] ?? ''), mb_strtolower($name)]);

        return $kind.':'.hash('sha256', $identity);
    }

    private function nullableValue(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }

    private function nullableInteger(?string $value): ?int
    {
        $value = $this->nullableValue($value);
        if ($value === null) {
            return null;
        }
        if (! preg_match('/^\d+$/', $value)) {
            throw new InvalidArgumentException('Expected a non-negative whole-number value.');
        }

        return (int) $value;
    }

    private function storedFile(PekerjaanImpor $job): UploadedFile
    {
        $path = Storage::disk('local')->path($job->file_path ?? '');
        if (! is_file($path)) {
            throw new InvalidArgumentException('The stored source file is unavailable.');
        }

        return new UploadedFile($path, $job->original_filename ?? basename($path), null, UPLOAD_ERR_OK, true);
    }

    private function saveProgress(PekerjaanImpor $job, int $processed, int $accepted, int $rejected, int $warnings): void
    {
        if ($processed % self::BATCH_SIZE === 0 || $processed === 1) {
            $job->forceFill([
                'processed_rows' => $processed, 'accepted_rows' => $accepted,
                'rejected_rows' => $rejected, 'warning_rows' => $warnings,
            ])->save();
        }
    }

    private function reportRow(mixed $stream, int $number, string $field, string $reason, string $value): void
    {
        fputcsv($stream, [$number, $field, $reason, preg_match('/^[\t\r\n ]*[=+@\-]/', $value) ? "'".$value : $value]);
    }

    public function markFailed(string $jobId, ?Throwable $exception): void
    {
        $job = PekerjaanImpor::query()->whereIn('target_type', ['facility_school', 'facility_market', 'facility_osm'])->find($jobId);
        if ($job !== null && ! in_array($job->status, ['completed', 'completed_with_errors', 'failed'], true)) {
            $job->forceFill(['status' => 'failed', 'failure_reason' => Str::limit($exception?->getMessage() ?? 'Import failed.', 1000), 'finished_at' => now()])->save();
            if ($job->pembuat !== null) {
                $this->auditLogService->record($job->pembuat, 'facility.import_failed', 'facility_import', $job->id, null, ['reason' => $job->failure_reason]);
            }
        }
    }
}
