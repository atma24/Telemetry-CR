<?php

namespace App\Services\Apbd;

use App\Enums\Wilayah\PublicationStatus;
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
use Throwable;

class ApbdImportService
{
    private const BATCH_SIZE = 500;

    private const MAX_ERROR_EXAMPLES = 100;

    public function __construct(
        private readonly ApbdSpreadsheetReader $spreadsheetReader,
        private readonly ApbdDataNormalizer $normalizer,
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @return array{import_job_id: string, original_filename: string, status: string, total_rows: int, accepted_rows: int, rejected_rows: int, rejection_reasons: array<string, int>, error_examples: array<int, array{row_number: int, errors: array<string, string>}>, error_report_url: string|null}
     */
    public function import(UploadedFile $file, Pengguna $actor, ?string $sourceId): array
    {
        $originalFilename = Str::limit(str_replace('\\', '/', $file->getClientOriginalName()), 255, '');
        $originalFilename = basename($originalFilename);
        $filePath = $file->store('imports/apbd/source', 'local');

        if ($filePath === false) {
            throw ValidationException::withMessages(['file' => 'The uploaded file could not be stored securely.']);
        }

        $job = PekerjaanImpor::query()->create([
            'target_type' => 'apbd',
            'source_id' => $sourceId,
            'original_filename' => $originalFilename,
            'file_path' => $filePath,
            'status' => 'processing',
            'initiated_by' => $actor->id,
        ]);

        $reportStream = tmpfile();

        if ($reportStream === false) {
            $this->failJob($job, $actor, 'Unable to create a private validation report.');

            throw ValidationException::withMessages(['file' => 'Unable to create a private validation report.']);
        }

        fputcsv($reportStream, ['row_number', 'field', 'reason', 'source_value'], ',', '"', '');

        $batch = [];
        $totalRows = 0;
        $acceptedRows = 0;
        $rejectedRows = 0;
        $reasonCounts = [];
        $examples = [];
        $reportPath = null;

        try {
            foreach ($this->spreadsheetReader->rows($file) as $sourceRow) {
                $totalRows++;

                try {
                    $normalized = $this->normalizer->normalizeRow($sourceRow['values']);
                    $batch[] = [
                        'row_number' => $sourceRow['row_number'],
                        'attributes' => $normalized,
                        'source_original_data' => $sourceRow['source_original_data'],
                        'fingerprint' => $this->normalizer->fingerprint($normalized),
                    ];
                } catch (ValidationException $exception) {
                    $errors = $this->validationMessages($exception);
                    $this->recordRejectedRow($sourceRow['row_number'], $errors, $sourceRow['source_original_data'], $reportStream, $reasonCounts, $examples);
                    $rejectedRows++;
                }

                if (count($batch) >= self::BATCH_SIZE) {
                    $duplicateRows = $this->persistBatch($batch, $job, $actor);
                    $acceptedRows += count($batch) - count($duplicateRows);
                    $rejectedRows += count($duplicateRows);

                    foreach ($duplicateRows as $duplicate) {
                        $this->recordRejectedRow(
                            $duplicate['row_number'],
                            ['duplicate' => 'Potential duplicate of an existing APBD record; the source row was not imported.'],
                            $duplicate['source_original_data'],
                            $reportStream,
                            $reasonCounts,
                            $examples,
                        );
                    }

                    $batch = [];
                }
            }

            if ($batch !== []) {
                $duplicateRows = $this->persistBatch($batch, $job, $actor);
                $acceptedRows += count($batch) - count($duplicateRows);
                $rejectedRows += count($duplicateRows);

                foreach ($duplicateRows as $duplicate) {
                    $this->recordRejectedRow(
                        $duplicate['row_number'],
                        ['duplicate' => 'Potential duplicate of an existing APBD record; the source row was not imported.'],
                        $duplicate['source_original_data'],
                        $reportStream,
                        $reasonCounts,
                        $examples,
                    );
                }
            }

            if ($rejectedRows > 0) {
                rewind($reportStream);
                $reportPath = "imports/apbd/errors/{$job->id}.csv";

                if (! Storage::disk('local')->writeStream($reportPath, $reportStream)) {
                    throw new InvalidArgumentException('The validation report could not be saved to private storage.');
                }
            }

            $status = $rejectedRows > 0 ? 'completed_with_errors' : 'completed';
            $job->forceFill([
                'status' => $status,
                'total_rows' => $totalRows,
                'accepted_rows' => $acceptedRows,
                'rejected_rows' => $rejectedRows,
                'error_report_path' => $reportPath,
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

            return [
                'import_job_id' => $job->id,
                'original_filename' => $job->original_filename,
                'status' => $status,
                'total_rows' => $totalRows,
                'accepted_rows' => $acceptedRows,
                'rejected_rows' => $rejectedRows,
                'rejection_reasons' => $reasonCounts,
                'error_examples' => $examples,
                'error_report_url' => $reportPath !== null
                    ? route('api.admin.apbd.imports.errors', ['importJob' => $job->id])
                    : null,
            ];
        } catch (InvalidArgumentException $exception) {
            $job->forceFill([
                'status' => 'failed',
                'total_rows' => $totalRows,
                'accepted_rows' => $acceptedRows,
                'rejected_rows' => $rejectedRows,
                'finished_at' => now(),
            ])->save();
            $this->auditLogService->record($actor, 'apbd.import_failed', 'apbd_import', $job->id, null, ['reason' => $exception->getMessage()]);

            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            $this->failJob($job, $actor, $exception->getMessage(), $totalRows, $acceptedRows, $rejectedRows);

            throw $exception;
        } finally {
            fclose($reportStream);
        }
    }

    /**
     * @param  array<int, array{row_number: int, attributes: array<string, string>, source_original_data: array<string, string>, fingerprint: string}>  $batch
     * @return array<int, array{row_number: int, source_original_data: array<string, string>}>
     */
    private function persistBatch(array $batch, PekerjaanImpor $job, Pengguna $actor): array
    {
        $fingerprints = array_values(array_unique(array_column($batch, 'fingerprint')));
        $existing = Apbd::query()->whereIn('source_fingerprint', $fingerprints)->pluck('source_fingerprint')->all();
        $seen = array_fill_keys($existing, true);
        $duplicates = [];
        $rows = [];
        $now = now();

        foreach ($batch as $candidate) {
            if (isset($seen[$candidate['fingerprint']])) {
                $duplicates[] = [
                    'row_number' => $candidate['row_number'],
                    'source_original_data' => $candidate['source_original_data'],
                ];

                continue;
            }

            $seen[$candidate['fingerprint']] = true;
            $rows[] = [
                'id' => (string) Str::uuid7(),
                ...$candidate['attributes'],
                'source_fingerprint' => $candidate['fingerprint'],
                'source_id' => $job->source_id,
                'import_job_id' => $job->id,
                'source_original_data' => json_encode($candidate['source_original_data'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'publication_status' => PublicationStatus::Draft->value,
                'created_by' => $actor->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::transaction(fn () => DB::table('apbd')->insert($rows));
        }

        return $duplicates;
    }

    /**
     * @param  array<string, string>  $errors
     * @param  array<string, string>  $sourceValues
     * @param  resource  $reportStream
     * @param  array<string, int>  $reasonCounts
     * @param  array<int, array{row_number: int, errors: array<string, string>}>  $examples
     */
    private function recordRejectedRow(
        int $rowNumber,
        array $errors,
        array $sourceValues,
        mixed $reportStream,
        array &$reasonCounts,
        array &$examples,
    ): void {
        foreach ($errors as $field => $reason) {
            $summaryKey = $field.': '.$reason;
            $reasonCounts[$summaryKey] = ($reasonCounts[$summaryKey] ?? 0) + 1;
            $rawValue = $sourceValues[$field] ?? '';

            fputcsv($reportStream, [
                $rowNumber,
                $field,
                $reason,
                $this->safeCsvCell($rawValue),
            ], ',', '"', '');
        }

        if (count($examples) < self::MAX_ERROR_EXAMPLES) {
            $examples[] = ['row_number' => $rowNumber, 'errors' => $errors];
        }
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

    private function failJob(
        PekerjaanImpor $job,
        Pengguna $actor,
        string $reason,
        int $totalRows = 0,
        int $acceptedRows = 0,
        int $rejectedRows = 0,
    ): void {
        $job->forceFill([
            'status' => 'failed',
            'total_rows' => $totalRows,
            'accepted_rows' => $acceptedRows,
            'rejected_rows' => $rejectedRows,
            'finished_at' => now(),
        ])->save();

        $this->auditLogService->record($actor, 'apbd.import_failed', 'apbd_import', $job->id, null, ['reason' => $reason]);
    }
}
