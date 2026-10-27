<?php

namespace App\Services\Apbd;

use App\Enums\Wilayah\PublicationStatus;
use App\Jobs\Apbd\ProcessApbdImport;
use App\Models\Apbd\Apbd;
use App\Models\PekerjaanImpor;
use App\Models\Pengguna;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class ApbdImportService
{
    private const BATCH_SIZE = 500;

    public function __construct(
        private readonly ApbdSpreadsheetReader $spreadsheetReader,
        private readonly ApbdDataNormalizer $normalizer,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function queueImport(UploadedFile $file, Pengguna $actor, ?string $sourceId): PekerjaanImpor
    {
        $originalFilename = Str::limit(str_replace('\\', '/', $file->getClientOriginalName()), 255, '');
        $originalFilename = basename($originalFilename);
        $filePath = $file->store('imports/apbd/source', 'local');

        if ($filePath === false) {
            throw ValidationException::withMessages(['file' => 'The uploaded file could not be stored securely.']);
        }

        $job = null;
        try {
            $job = PekerjaanImpor::query()->create([
                'target_type' => 'apbd',
                'source_id' => $sourceId,
                'original_filename' => $originalFilename,
                'file_path' => $filePath,
                'status' => 'uploaded',
                'processed_rows' => 0,
                'initiated_by' => $actor->id,
            ]);

            ProcessApbdImport::dispatch($job->id);

            return $job;
        } catch (Throwable $exception) {
            if ($job instanceof PekerjaanImpor) {
                $this->markFailed($job->id, $exception);
            } else {
                Storage::disk('local')->delete($filePath);
            }

            throw $exception;
        }
    }

    public function process(string $importJobId): void
    {
        $job = PekerjaanImpor::query()
            ->where('target_type', 'apbd')
            ->findOrFail($importJobId);

        if (in_array($job->status, ['completed', 'completed_with_errors', 'failed'], true)) {
            return;
        }

        $actor = $job->pembuat;

        if ($actor === null) {
            $this->markFailed($job->id, new InvalidArgumentException('The importing Admin account is unavailable.'));

            return;
        }

        $job->forceFill([
            'status' => 'processing',
            'total_rows' => null,
            'processed_rows' => 0,
            'accepted_rows' => 0,
            'rejected_rows' => 0,
            'error_report_path' => null,
            'failure_reason' => null,
            'finished_at' => null,
        ])->save();

        $reportStream = null;

        try {
            if ($job->file_path === null) {
                throw new InvalidArgumentException('The stored APBD file is unavailable.');
            }

            $sourcePath = Storage::disk('local')->path($job->file_path);

            if (! is_file($sourcePath)) {
                throw new InvalidArgumentException('The stored APBD file is unavailable.');
            }

            $file = new UploadedFile(
                $sourcePath,
                $job->original_filename ?? basename($sourcePath),
                null,
                UPLOAD_ERR_OK,
                true,
            );
            $totalRows = $this->countRows($file);
            $job->forceFill(['total_rows' => $totalRows])->save();
            $reportStream = tmpfile();

            if ($reportStream === false) {
                throw new RuntimeException('Unable to create a private validation report.');
            }

            if (fputcsv($reportStream, ['row_number', 'field', 'reason', 'source_value'], ',', '"', '') === false) {
                throw new RuntimeException('Unable to write the private validation report.');
            }

            $batch = [];
            $seenFingerprints = [];
            $processedRows = 0;
            $batchProcessedRows = 0;
            $batchRejectedRows = 0;
            $acceptedRows = 0;
            $rejectedRows = 0;

            foreach ($this->spreadsheetReader->rows($file) as $sourceRow) {
                $processedRows++;
                $batchProcessedRows++;

                try {
                    $normalized = $this->normalizer->normalizeRow($sourceRow['values']);
                    $fingerprint = $this->normalizer->fingerprint($normalized);

                    if (isset($seenFingerprints[$fingerprint])) {
                        $this->recordRejectedRow(
                            $sourceRow['row_number'],
                            ['duplicate' => 'Potential duplicate within this APBD import; the source row was not imported.'],
                            $sourceRow['source_original_data'],
                            $reportStream,
                        );
                        $batchRejectedRows++;
                    } else {
                        $seenFingerprints[$fingerprint] = true;
                        $batch[] = [
                            'row_number' => $sourceRow['row_number'],
                            'attributes' => $normalized,
                            'source_original_data' => $sourceRow['source_original_data'],
                            'fingerprint' => $fingerprint,
                        ];
                    }
                } catch (ValidationException $exception) {
                    $this->recordRejectedRow(
                        $sourceRow['row_number'],
                        $this->validationMessages($exception),
                        $sourceRow['source_original_data'],
                        $reportStream,
                    );
                    $batchRejectedRows++;
                }

                if ($batchProcessedRows >= self::BATCH_SIZE) {
                    [$acceptedRows, $rejectedRows] = $this->persistProgressBatch(
                        $batch,
                        $job,
                        $actor,
                        $reportStream,
                        $processedRows,
                        $acceptedRows,
                        $rejectedRows,
                        $batchRejectedRows,
                    );
                    $batch = [];
                    $batchProcessedRows = 0;
                    $batchRejectedRows = 0;
                }
            }

            if ($batchProcessedRows > 0) {
                [$acceptedRows, $rejectedRows] = $this->persistProgressBatch(
                    $batch,
                    $job,
                    $actor,
                    $reportStream,
                    $processedRows,
                    $acceptedRows,
                    $rejectedRows,
                    $batchRejectedRows,
                );
            }

            $reportPath = null;

            if ($rejectedRows > 0) {
                $reportPath = "imports/apbd/errors/{$job->id}.csv";
                Storage::disk('local')->delete($reportPath);
                rewind($reportStream);

                if (! Storage::disk('local')->writeStream($reportPath, $reportStream)) {
                    throw new RuntimeException('The validation report could not be saved to private storage.');
                }
            }

            $status = $rejectedRows > 0 ? 'completed_with_errors' : 'completed';

            DB::transaction(function () use ($job, $actor, $status, $totalRows, $acceptedRows, $rejectedRows, $reportPath): void {
                $job->forceFill([
                    'status' => $status,
                    'total_rows' => $totalRows,
                    'processed_rows' => $totalRows,
                    'accepted_rows' => $acceptedRows,
                    'rejected_rows' => $rejectedRows,
                    'error_report_path' => $reportPath,
                    'failure_reason' => null,
                    'finished_at' => now(),
                ])->save();

                $this->auditLogService->record(
                    $actor,
                    'apbd.imported',
                    'apbd_import',
                    $job->id,
                    null,
                    [
                        'status' => $status,
                        'total_rows' => $totalRows,
                        'accepted_rows' => $acceptedRows,
                        'rejected_rows' => $rejectedRows,
                    ],
                );
            });
        } catch (InvalidArgumentException $exception) {
            $this->markFailed($job->id, $exception);
        } finally {
            if (is_resource($reportStream)) {
                fclose($reportStream);
            }
        }
    }

    public function markFailed(string $importJobId, ?Throwable $exception): void
    {
        $job = PekerjaanImpor::query()->find($importJobId);

        if ($job === null || in_array($job->status, ['completed', 'completed_with_errors', 'failed'], true)) {
            return;
        }

        $reason = Str::limit($exception?->getMessage() ?: 'The APBD import could not be completed.', 1000, '');
        $actor = $job->pembuat;

        DB::transaction(function () use ($job, $actor, $reason): void {
            $job->forceFill([
                'status' => 'failed',
                'failure_reason' => $reason,
                'finished_at' => now(),
            ])->save();

            if ($actor !== null) {
                $this->auditLogService->record(
                    $actor,
                    'apbd.import_failed',
                    'apbd_import',
                    $job->id,
                    null,
                    ['reason' => $reason],
                );
            }
        });
    }

    /**
     * @param  array<int, array{row_number: int, attributes: array<string, string>, source_original_data: array<string, string>, fingerprint: string}>  $batch
     * @return array<int, array{row_number: int, source_original_data: array<string, string>}>
     */
    private function persistBatch(
        array $batch,
        PekerjaanImpor $job,
        Pengguna $actor,
        int $processedRows,
        int $acceptedRows,
        int $rejectedRows,
        int $batchRejectedRows,
    ): array {
        $fingerprints = array_values(array_unique(array_column($batch, 'fingerprint')));

        return DB::transaction(function () use (
            $batch,
            $fingerprints,
            $job,
            $actor,
            $processedRows,
            $acceptedRows,
            $rejectedRows,
            $batchRejectedRows,
        ): array {
            $existingByFingerprint = [];

            if ($fingerprints !== []) {
                foreach (Apbd::query()
                    ->whereIn('source_fingerprint', $fingerprints)
                    ->get(['source_fingerprint', 'import_job_id']) as $existingRecord) {
                    $fingerprint = $existingRecord->source_fingerprint;
                    $belongsToThisJob = $existingRecord->import_job_id === $job->id;
                    $existingByFingerprint[$fingerprint] = ($existingByFingerprint[$fingerprint] ?? false) || $belongsToThisJob;
                }
            }

            $duplicates = [];
            $rows = [];
            $now = now();

            foreach ($batch as $candidate) {
                $fingerprint = $candidate['fingerprint'];

                if (array_key_exists($fingerprint, $existingByFingerprint)) {
                    if (! $existingByFingerprint[$fingerprint]) {
                        $duplicates[] = [
                            'row_number' => $candidate['row_number'],
                            'source_original_data' => $candidate['source_original_data'],
                        ];
                    }

                    continue;
                }

                $existingByFingerprint[$fingerprint] = true;
                $rows[] = [
                    'id' => (string) Str::uuid7(),
                    ...$candidate['attributes'],
                    'source_fingerprint' => $fingerprint,
                    'source_id' => $job->source_id,
                    'import_job_id' => $job->id,
                    'source_original_data' => json_encode($candidate['source_original_data'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'publication_status' => PublicationStatus::Published->value,
                    'created_by' => $actor->id,
                    'reviewed_by' => $actor->id,
                    'published_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($rows !== []) {
                DB::table('apbd')->insert($rows);
            }

            $nextAcceptedRows = $acceptedRows + count($batch) - count($duplicates);
            $nextRejectedRows = $rejectedRows + $batchRejectedRows + count($duplicates);

            $job->forceFill([
                'processed_rows' => $processedRows,
                'accepted_rows' => $nextAcceptedRows,
                'rejected_rows' => $nextRejectedRows,
            ])->save();

            return $duplicates;
        });
    }

    /**
     * @param  array<int, array{row_number: int, attributes: array<string, string>, source_original_data: array<string, string>, fingerprint: string}>  $batch
     * @return array{0: int, 1: int}
     */
    private function persistProgressBatch(
        array $batch,
        PekerjaanImpor $job,
        Pengguna $actor,
        mixed $reportStream,
        int $processedRows,
        int $acceptedRows,
        int $rejectedRows,
        int $batchRejectedRows,
    ): array {
        $duplicateRows = $this->persistBatch(
            $batch,
            $job,
            $actor,
            $processedRows,
            $acceptedRows,
            $rejectedRows,
            $batchRejectedRows,
        );
        $acceptedRows += count($batch) - count($duplicateRows);
        $rejectedRows += $batchRejectedRows + count($duplicateRows);

        foreach ($duplicateRows as $duplicate) {
            $this->recordRejectedRow(
                $duplicate['row_number'],
                ['duplicate' => 'Potential duplicate of an existing APBD record; the source row was not imported.'],
                $duplicate['source_original_data'],
                $reportStream,
            );
        }

        return [$acceptedRows, $rejectedRows];
    }

    /**
     * @param  array<string, string>  $errors
     * @param  array<string, string>  $sourceValues
     * @param  resource  $reportStream
     */
    private function recordRejectedRow(
        int $rowNumber,
        array $errors,
        array $sourceValues,
        mixed $reportStream,
    ): void {
        foreach ($errors as $field => $reason) {
            $rawValue = $sourceValues[$field] ?? '';

            if (fputcsv($reportStream, [
                $rowNumber,
                $field,
                $reason,
                $this->safeCsvCell($rawValue),
            ], ',', '"', '') === false) {
                throw new RuntimeException('Unable to write the private validation report.');
            }
        }
    }

    private function countRows(UploadedFile $file): int
    {
        $totalRows = 0;

        foreach ($this->spreadsheetReader->rows($file) as $_row) {
            $totalRows++;
        }

        return $totalRows;
    }

    /** @return array<string, string> */
    private function validationMessages(ValidationException $exception): array
    {
        $messages = [];

        foreach ($exception->errors() as $field => $errors) {
            $messages[$field] = implode(' ', $errors);
        }

        return $messages;
    }

    private function safeCsvCell(string $value): string
    {
        return preg_match('/^[\t\r\n ]*[=+@\-]/', $value) ? "'".$value : $value;
    }
}
