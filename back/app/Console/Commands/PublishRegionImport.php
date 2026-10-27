<?php

namespace App\Console\Commands;

use App\Enums\Wilayah\PublicationStatus;
use App\Models\Pengguna;
use App\Models\Wilayah\VersiWilayah;
use App\Services\Wilayah\RegionGeoJsonDocument;
use App\Services\Wilayah\RegionGeometry;
use App\Services\Wilayah\WilayahService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

#[Signature('app:publish-region-import
    {--file=regions/jakarta/regions.geojson : Prepared GeoJSON path on the local disk}
    {--actor= : Email of the active Admin who validates and publishes the regions}
    {--dry-run : Report the planned actions without writing anything}')]
#[Description('Validate and publish prepared region boundaries in province, city/regency, district, village order')]
class PublishRegionImport extends Command
{
    /** @var array<string, array<string, int>> */
    private array $counts = [];

    public function __construct(
        private readonly WilayahService $wilayahService,
        private readonly RegionGeometry $regionGeometry,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $path = Storage::disk('local')->path(trim((string) $this->option('file')));

        try {
            $document = RegionGeoJsonDocument::read($path);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $actor = null;

        if (! $dryRun) {
            $actor = $this->actor();

            if ($actor === null) {
                return self::FAILURE;
            }
        }

        $published = VersiWilayah::query()
            ->terpublikasi()
            ->get(['code', 'level'])
            ->mapWithKeys(fn (VersiWilayah $region): array => [$region->code => $region->level])
            ->all();

        $hasFailures = false;

        foreach ($document->entriesByLevel() as $level => $entries) {
            if ($entries === []) {
                continue;
            }

            $this->counts[$level] = [
                'planned' => count($entries),
                'created' => 0,
                'validated' => 0,
                'published' => 0,
                'already_published' => 0,
                'failed' => 0,
            ];

            foreach ($entries as $entry) {
                try {
                    $result = $dryRun
                        ? $this->simulate($entry, $published)
                        : $this->publish($entry, $actor);

                    $this->counts[$level][$result]++;

                    $published[$entry['code']] = $entry['level'];
                } catch (Throwable $exception) {
                    $hasFailures = true;
                    $this->counts[$level]['failed']++;
                    $this->error("[{$level}] {$entry['code']} {$entry['name']}: {$exception->getMessage()}");
                }
            }
        }

        $this->table(
            ['Level', 'Planned', 'Created', 'Validated', 'Published', 'Already published', 'Failed'],
            collect($this->counts)
                ->map(fn (array $counts, string $level): array => [
                    $level,
                    $counts['planned'],
                    $counts['created'],
                    $counts['validated'],
                    $counts['published'],
                    $counts['already_published'],
                    $counts['failed'],
                ])
                ->values()
                ->all(),
        );

        $this->info($dryRun
            ? 'Dry run complete. No region data was written.'
            : 'Region import publication complete.');

        return $hasFailures ? self::FAILURE : self::SUCCESS;
    }

    private function actor(): ?Pengguna
    {
        $email = trim((string) $this->option('actor'));

        if ($email === '') {
            $this->error('The --actor option is required unless --dry-run is used.');

            return null;
        }

        $actor = Pengguna::query()->where('email', $email)->first();

        if ($actor === null || ! $actor->can('admin')) {
            $this->error("No active Admin account matches [{$email}].");

            return null;
        }

        return $actor;
    }

    /**
     * @param  array{code: string, name: string, level: string, parent_code: string|null, geometry: array<string, mixed>}  $entry
     * @param  array<string, string>  $published
     */
    private function simulate(array $entry, array $published): string
    {
        if (($published[$entry['code']] ?? null) === $entry['level']) {
            return 'already_published';
        }

        $existing = VersiWilayah::query()
            ->where('code', $entry['code'])
            ->orderByDesc('version_no')
            ->first(['id', 'version_no', 'level', 'publication_status', 'geometry']);

        if ($existing !== null && $existing->level !== $entry['level']) {
            throw new InvalidArgumentException("Existing region has level [{$existing->level}].");
        }

        if ($existing !== null && ! $this->hasValidGeometry($existing)) {
            throw new InvalidArgumentException('Existing draft has no valid boundary geometry; review it before publishing.');
        }

        if ($entry['parent_code'] !== null && ! isset($published[$entry['parent_code']])) {
            throw new InvalidArgumentException("Parent [{$entry['parent_code']}] is not published before this level.");
        }

        return match ($existing?->publication_status) {
            null, PublicationStatus::Superseded, PublicationStatus::Archived => 'created',
            PublicationStatus::Draft => 'validated',
            PublicationStatus::Validating => 'published',
            PublicationStatus::Published => 'already_published',
            default => throw new InvalidArgumentException('Existing region is in an unsupported publication state.'),
        };
    }

    /**
     * @param  array{code: string, name: string, level: string, parent_code: string|null, geometry: array<string, mixed>}  $entry
     */
    private function publish(array $entry, Pengguna $actor): string
    {
        return DB::transaction(function () use ($entry, $actor): string {
            $existing = VersiWilayah::query()
                ->where('code', $entry['code'])
                ->orderByDesc('version_no')
                ->lockForUpdate()
                ->first();

            if ($existing?->publication_status === PublicationStatus::Published) {
                if ($existing->level !== $entry['level']) {
                    throw new InvalidArgumentException("Published region has level [{$existing->level}].");
                }

                return 'already_published';
            }

            $parentId = null;

            if ($entry['parent_code'] !== null) {
                $parentId = VersiWilayah::query()
                    ->terpublikasi()
                    ->where('code', $entry['parent_code'])
                    ->value('region_id');

                if (! is_string($parentId)) {
                    throw new InvalidArgumentException("Parent [{$entry['parent_code']}] is not published.");
                }
            }

            $attributes = [
                'code' => $entry['code'],
                'name' => $entry['name'],
                'level' => $entry['level'],
                'parent_region_id' => $parentId,
                'geometry' => $entry['geometry'],
                'source_id' => null,
                'source_original_data' => [
                    'kode_wilayah' => $entry['code'],
                    'nama_wilayah' => $entry['name'],
                    'tingkat_wilayah' => $entry['level'],
                    'kode_wilayah_induk' => $entry['parent_code'],
                ],
            ];

            if ($existing === null) {
                $version = $this->wilayahService->createDraft($attributes, $actor);
                $action = 'created';
            } else {
                if ($existing->level !== $entry['level']) {
                    throw new InvalidArgumentException("Existing region has level [{$existing->level}].");
                }

                if (
                    in_array($existing->publication_status, [PublicationStatus::Draft, PublicationStatus::Validating], true)
                    && $existing->parent_region_id !== $parentId
                ) {
                    throw new InvalidArgumentException('Existing region parent does not match the prepared hierarchy.');
                }

                if ($existing->publication_status === PublicationStatus::Draft) {
                    if (! $this->hasValidGeometry($existing)) {
                        throw new InvalidArgumentException('Existing draft has no valid boundary geometry; review it before publishing.');
                    }

                    $version = $existing;
                    $action = 'validated';
                } elseif ($existing->publication_status === PublicationStatus::Validating) {
                    if (! $this->hasValidGeometry($existing)) {
                        throw new InvalidArgumentException('Existing validating region has no valid boundary geometry; review it before publishing.');
                    }

                    $version = $existing;
                    $action = 'published';
                } else {
                    $version = $this->wilayahService->createVersion($existing->region_id, $attributes, $actor);
                    $action = 'created';
                }
            }

            if ($version->publication_status === PublicationStatus::Draft) {
                $version = $this->wilayahService->validateVersion($version->region_id, $version->id, $actor);
            }

            $this->wilayahService->publishVersion($version->region_id, $version->id, $actor);

            return $action;
        });
    }

    private function hasValidGeometry(VersiWilayah $version): bool
    {
        return is_array($version->geometry) && $this->regionGeometry->isValid($version->geometry);
    }
}
