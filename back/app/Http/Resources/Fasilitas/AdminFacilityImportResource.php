<?php

namespace App\Http\Resources\Fasilitas;

use App\Models\PekerjaanImpor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/** @mixin PekerjaanImpor */
class AdminFacilityImportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $totalRows = $this->total_rows;

        return [
            'id' => $this->id,
            'dataset_type' => $this->target_type,
            'accepted_headers' => match ($this->target_type) {
                'facility_school' => ['periode_data', 'npsn', 'nama_sekolah', 'alamat', 'kelurahan', 'kecamatan', 'jumlah_siswa', 'jumlah_guru', 'kepala_sekolah', 'telp_sekolah', 'akreditasi', 'latitude', 'longitude'],
                'facility_market' => ['periode_data', 'wilayah', 'kecamatan', 'kelurahan', 'nama_pasar', 'alamat', 'kode_pos', 'telepon', 'koordinat_x', 'koordinat_y'],
                default => ['FeatureCollection.features[].geometry', 'FeatureCollection.features[].properties.@id|id|osm_id', 'properties.name|operator|brand', 'properties.amenity|shop|leisure|healthcare|office', 'properties.address|addr:full|addr:street'],
            },
            'original_filename' => $this->original_filename,
            'status' => $this->status,
            'processed_rows' => $this->processed_rows,
            'total_rows' => $totalRows,
            'accepted_rows' => $this->accepted_rows,
            'rejected_rows' => $this->rejected_rows,
            'warning_rows' => $this->warning_rows,
            'progress_percentage' => $totalRows === null ? null : ($totalRows === 0 ? 100 : round(($this->processed_rows / $totalRows) * 100, 1)),
            'failure_reason' => $this->failure_reason,
            'status_url' => route('api.admin.facilities.imports.show', ['importJob' => $this->id]),
            'error_report_url' => $this->error_report_path !== null
                ? route('api.admin.facilities.imports.errors', ['importJob' => $this->id])
                : null,
            'created_at' => $this->created_at?->toISOString(),
            'finished_at' => $this->finished_at !== null ? Carbon::parse($this->finished_at)->toISOString() : null,
        ];
    }
}
