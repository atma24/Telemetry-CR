<?php

namespace App\Http\Resources\Apbd;

use App\Models\PekerjaanImpor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/** @mixin PekerjaanImpor */
class AdminApbdImportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $totalRows = $this->total_rows;

        return [
            'id' => $this->id,
            'original_filename' => $this->original_filename,
            'status' => $this->status,
            'processed_rows' => $this->processed_rows ?? 0,
            'total_rows' => $totalRows,
            'accepted_rows' => $this->accepted_rows,
            'rejected_rows' => $this->rejected_rows,
            'progress_percentage' => $totalRows === null
                ? null
                : ($totalRows === 0 ? 100 : round(($this->processed_rows / $totalRows) * 100, 1)),
            'failure_reason' => $this->failure_reason,
            'status_url' => route('api.admin.apbd.imports.show', ['importJob' => $this->id]),
            'error_report_url' => $this->error_report_path !== null
                ? route('api.admin.apbd.imports.errors', ['importJob' => $this->id])
                : null,
            'created_at' => $this->created_at?->toISOString(),
            'finished_at' => $this->finished_at !== null
                ? Carbon::parse($this->finished_at)->toISOString()
                : null,
        ];
    }
}
