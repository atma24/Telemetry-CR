<?php

namespace App\Services\Apbd;

use App\Enums\Wilayah\PublicationStatus;
use App\Models\Apbd\Apbd;
use App\Models\Pengguna;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminApbdService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly ApbdDataNormalizer $normalizer,
    ) {}

    /** @param array<string, string> $attributes */
    public function createDraft(array $attributes, Pengguna $actor): Apbd
    {
        return DB::transaction(function () use ($attributes, $actor): Apbd {
            $attributes = $this->normalizer->normalizeRow($attributes);
            $record = Apbd::query()->create([
                ...$attributes,
                'source_fingerprint' => $this->normalizer->fingerprint($attributes),
                'publication_status' => PublicationStatus::Draft,
                'created_by' => $actor->id,
            ]);

            $this->auditLogService->record(
                $actor,
                'apbd.created',
                'apbd',
                $record->id,
                null,
                $this->auditData($record),
            );

            return $record;
        });
    }

    /** @param array<string, string> $attributes */
    public function update(string $apbdId, array $attributes, Pengguna $actor): Apbd
    {
        return DB::transaction(function () use ($apbdId, $attributes, $actor): Apbd {
            $record = Apbd::query()->whereKey($apbdId)->lockForUpdate()->firstOrFail();
            $currentData = $record->only([
                'periode_data', 'tanggal', 'kode_opd', 'nama_opd', 'akun_jenis', 'nama_akun', 'anggaran', 'realisasi',
            ]);
            $currentData['tanggal'] = (string) $record->getRawOriginal('tanggal');
            $data = array_merge($currentData, $attributes);
            $data = array_map(static fn (mixed $value): string => (string) $value, $data);
            $data = $this->normalizer->normalizeRow($data);
            $fingerprint = $this->normalizer->fingerprint($data);
            $oldData = $this->auditData($record);

            if ($record->publication_status === PublicationStatus::Published) {
                $updated = Apbd::query()->create([
                    ...$data,
                    'source_fingerprint' => $fingerprint,
                    'revises_id' => $record->id,
                    'source_id' => $record->source_id,
                    'import_job_id' => $record->import_job_id,
                    'source_original_data' => $record->source_original_data,
                    'publication_status' => PublicationStatus::Draft,
                    'created_by' => $actor->id,
                ]);

                $this->auditLogService->record(
                    $actor,
                    'apbd.revision_created',
                    'apbd',
                    $record->id,
                    $oldData,
                    $this->auditData($updated),
                );

                return $updated;
            }

            if ($record->publication_status !== PublicationStatus::Draft) {
                throw ValidationException::withMessages([
                    'publication_status' => 'Only draft or published APBD records can be edited.',
                ]);
            }

            $record->forceFill([
                ...$data,
                'source_fingerprint' => $fingerprint,
            ])->save();

            $this->auditLogService->record(
                $actor,
                'apbd.updated',
                'apbd',
                $record->id,
                $oldData,
                $this->auditData($record),
            );

            return $record->refresh();
        });
    }

    public function validate(string $apbdId, Pengguna $actor): Apbd
    {
        return DB::transaction(function () use ($apbdId, $actor): Apbd {
            $record = Apbd::query()->whereKey($apbdId)->lockForUpdate()->firstOrFail();

            if ($record->publication_status !== PublicationStatus::Draft) {
                throw ValidationException::withMessages([
                    'publication_status' => 'Only draft APBD records can be validated.',
                ]);
            }

            $record->forceFill([
                'publication_status' => PublicationStatus::Validating,
                'reviewed_by' => $actor->id,
            ])->save();

            $this->auditLogService->record(
                $actor,
                'apbd.validated',
                'apbd',
                $record->id,
                ['publication_status' => PublicationStatus::Draft->value],
                ['publication_status' => PublicationStatus::Validating->value],
            );

            return $record->refresh();
        });
    }

    public function publish(string $apbdId, Pengguna $actor): Apbd
    {
        return DB::transaction(function () use ($apbdId, $actor): Apbd {
            $record = Apbd::query()->whereKey($apbdId)->lockForUpdate()->firstOrFail();

            if ($record->publication_status !== PublicationStatus::Validating) {
                throw ValidationException::withMessages([
                    'publication_status' => 'Only validated APBD records can be published.',
                ]);
            }

            if ($record->revises_id !== null) {
                $previous = Apbd::query()->whereKey($record->revises_id)->lockForUpdate()->firstOrFail();

                if ($previous->publication_status !== PublicationStatus::Published) {
                    throw ValidationException::withMessages([
                        'revises_id' => 'The published APBD record being corrected is no longer current.',
                    ]);
                }

                $previous->forceFill(['publication_status' => PublicationStatus::Superseded])->save();
            }

            $record->forceFill([
                'publication_status' => PublicationStatus::Published,
                'reviewed_by' => $actor->id,
                'published_at' => now(),
            ])->save();

            $this->auditLogService->record(
                $actor,
                'apbd.published',
                'apbd',
                $record->id,
                null,
                ['publication_status' => PublicationStatus::Published->value, 'revises_id' => $record->revises_id],
            );

            return $record->refresh();
        });
    }

    public function delete(string $apbdId, Pengguna $actor): void
    {
        DB::transaction(function () use ($apbdId, $actor): void {
            $record = Apbd::query()->whereKey($apbdId)->lockForUpdate()->firstOrFail();
            $oldData = $this->auditData($record);

            if ($record->publication_status === PublicationStatus::Published) {
                $record->forceFill(['publication_status' => PublicationStatus::Archived])->save();

                $this->auditLogService->record(
                    $actor,
                    'apbd.archived',
                    'apbd',
                    $record->id,
                    $oldData,
                    ['publication_status' => PublicationStatus::Archived->value],
                );

                return;
            }

            if ($record->publication_status !== PublicationStatus::Draft) {
                throw ValidationException::withMessages([
                    'publication_status' => 'Only draft or published APBD records can be deleted.',
                ]);
            }

            $this->auditLogService->record($actor, 'apbd.deleted', 'apbd', $record->id, $oldData, null);
            $record->delete();
        });
    }

    /** @return array<string, mixed> */
    private function auditData(Apbd $record): array
    {
        return $record->only([
            'id', 'periode_data', 'tanggal', 'kode_opd', 'nama_opd', 'akun_jenis', 'nama_akun', 'anggaran', 'realisasi',
            'publication_status', 'revises_id', 'source_id', 'import_job_id', 'source_original_data',
        ]);
    }
}
