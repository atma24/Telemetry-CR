<?php

namespace App\Console\Commands;

use App\Enums\Wilayah\PublicationStatus;
use App\Models\Fasilitas\VersiFasilitas;
use App\Models\Pengguna;
use App\Models\Wilayah\VersiWilayah;
use App\Services\Audit\AuditLogService;
use App\Services\Wilayah\RegionMatcher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

#[Signature('app:reconcile-facility-regions
    {--apply : Write the matched region IDs to draft facility versions}
    {--actor= : Email of the active Admin whose action is recorded when --apply is used}
    {--dataset= : Limit reconciliation to school or market}
    {--limit=0 : Stop after this many draft facility versions for a trial run}')]
#[Description('Match draft schools and markets to published villages by exact village and district pair')]
class ReconcileFacilityRegions extends Command
{
    private const SAMPLE_LIMIT = 20;

    /** @var array<string, array{scanned: int, matched: int, applied: int}> */
    private array $stats = [
        'school' => ['scanned' => 0, 'matched' => 0, 'applied' => 0],
        'market' => ['scanned' => 0, 'matched' => 0, 'applied' => 0],
    ];

    /** @var array<string, int> */
    private array $unmatchedReasons = [];

    /** @var list<array{name: string, village: string, district: string, reason: string}> */
    private array $unmatchedSamples = [];

    public function __construct(
        private readonly RegionMatcher $regionMatcher,
        private readonly AuditLogService $auditLogService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dataset = strtolower(trim((string) $this->option('dataset')));

        if ($dataset !== '' && ! in_array($dataset, ['school', 'market'], true)) {
            $this->error('The --dataset option must be school or market.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $actor = null;

        if ($apply) {
            $actor = $this->actor();

            if ($actor === null) {
                return self::FAILURE;
            }
        }

        if (! VersiWilayah::query()->terpublikasi()->where('level', 'kelurahan')->exists()) {
            $this->error('No published villages exist. Publish the region boundaries before reconciling facilities.');

            return self::FAILURE;
        }

        $limit = max(0, (int) $this->option('limit'));
        $scanned = 0;
        $failures = 0;

        $query = VersiFasilitas::query()
            ->where('publication_status', PublicationStatus::Draft)
            ->whereNull('region_id')
            ->with(['sekolah', 'lokasiPasar'])
            ->orderBy('id');

        if ($dataset === 'school') {
            $query->whereHas('sekolah');
        } elseif ($dataset === 'market') {
            $query->whereHas('lokasiPasar');
        } else {
            $query->where(fn ($builder) => $builder->whereHas('sekolah')->orWhereHas('lokasiPasar'));
        }

        foreach ($query->lazyById(250) as $version) {
            if ($limit > 0 && $scanned >= $limit) {
                break;
            }

            $scanned++;
            $kind = $version->sekolah !== null ? 'school' : 'market';
            $this->stats[$kind]['scanned']++;

            [$village, $district] = $this->locality($version);
            $regionId = $this->regionMatcher->match($village, $district);

            if ($regionId === null) {
                $reason = $this->unmatchedReason($village, $district);
                $this->unmatchedReasons[$reason] = ($this->unmatchedReasons[$reason] ?? 0) + 1;

                if (count($this->unmatchedSamples) < self::SAMPLE_LIMIT) {
                    $this->unmatchedSamples[] = [
                        'name' => $version->name,
                        'village' => $village ?? '',
                        'district' => $district ?? '',
                        'reason' => $reason,
                    ];
                }

                continue;
            }

            $this->stats[$kind]['matched']++;

            if (! $apply) {
                continue;
            }

            try {
                $applied = DB::transaction(function () use ($version, $regionId, $actor, $village, $district): bool {
                    $locked = VersiFasilitas::query()->whereKey($version->id)->lockForUpdate()->first();

                    if ($locked === null || $locked->publication_status !== PublicationStatus::Draft || $locked->region_id !== null) {
                        return false;
                    }

                    $locked->forceFill(['region_id' => $regionId])->save();

                    $this->auditLogService->record(
                        $actor,
                        'facility.region_reconciled',
                        'facility_version',
                        $locked->id,
                        ['region_id' => null],
                        [
                            'region_id' => $regionId,
                            'kelurahan_sumber' => $village,
                            'kecamatan_sumber' => $district,
                        ],
                    );

                    return true;
                });

                if ($applied) {
                    $this->stats[$kind]['applied']++;
                }
            } catch (Throwable $exception) {
                $failures++;
                $this->error("{$version->name} ({$version->id}): {$exception->getMessage()}");
            }
        }

        $this->table(['Dataset', 'Scanned', 'Matched', 'Applied'], collect($this->stats)
            ->map(fn (array $stats, string $kind): array => [$kind, $stats['scanned'], $stats['matched'], $stats['applied']])
            ->values()
            ->all());

        if ($this->unmatchedReasons !== []) {
            $this->table(['Unmatched reason', 'Count'], collect($this->unmatchedReasons)
                ->map(fn (int $count, string $reason): array => [$reason, $count])
                ->values()
                ->all());

            $this->table(['Facility', 'Village source', 'District source', 'Reason'], array_map(
                fn (array $sample): array => [$sample['name'], $sample['village'], $sample['district'], $sample['reason']],
                $this->unmatchedSamples,
            ));
        }

        $this->info(match (true) {
            $apply => 'Reconciliation applied to draft facility versions. Published values were not changed.',
            $scanned === 0 => 'No draft facility versions with a null region were found.',
            default => 'Dry run complete. No facility data was written. Re-run with --apply --actor=email after reviewing the summary.',
        });

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function actor(): ?Pengguna
    {
        $email = trim((string) $this->option('actor'));

        if ($email === '') {
            $this->error('The --actor option is required when --apply is used.');

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
     * @return array{0: string|null, 1: string|null}
     */
    private function locality(VersiFasilitas $version): array
    {
        if ($version->sekolah !== null) {
            return [$version->sekolah->kelurahan_sumber, $version->sekolah->kecamatan_sumber];
        }

        if ($version->lokasiPasar !== null) {
            return [$version->lokasiPasar->kelurahan_sumber, $version->lokasiPasar->kecamatan_sumber];
        }

        return [null, null];
    }

    private function unmatchedReason(?string $village, ?string $district): string
    {
        if ($village === null || trim($village) === '' || $district === null || trim($district) === '') {
            return 'Source village or district is missing.';
        }

        return 'No unique published village and district pair matches the source spelling.';
    }
}
