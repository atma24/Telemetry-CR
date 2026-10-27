<?php

namespace App\Http\Resources\Wilayah;

use App\Models\PekerjaanImpor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PekerjaanImpor */
class AdminRegionImportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $total = $this->total_rows;

        return [
            'id' => $this->id,
            'accepted_headers' => ['properties.kode_wilayah|code', 'properties.nama_wilayah|name', 'properties.tingkat_wilayah|level', 'properties.kode_wilayah_induk|parent_code', 'geometry'],
            'original_filename' => $this->original_filename,
            'status' => $this->status,
            'processed_rows' => $this->processed_rows,
            'total_rows' => $total,
            'accepted_rows' => $this->accepted_rows,
            'rejected_rows' => $this->rejected_rows,
            'warning_rows' => $this->warning_rows,
            'progress_percentage' => $total === null ? null : ($total === 0 ? 100 : round(($this->processed_rows / $total) * 100, 1)),
            'failure_reason' => $this->failure_reason,
            'status_url' => route('api.admin.regions.imports.show', ['importJob' => $this->id]),
            'error_report_url' => $this->error_report_path === null ? null : route('api.admin.regions.imports.errors', ['importJob' => $this->id]),
        ];
    }
}
