<?php

namespace App\Http\Resources\Apbd;

use App\Models\Apbd\Apbd;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/** @mixin Apbd */
class AdminApbdResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'periode_data' => $this->periode_data,
            'tanggal' => $this->tanggal instanceof Carbon ? $this->tanggal->toDateString() : (string) $this->tanggal,
            'kode_opd' => $this->kode_opd,
            'nama_opd' => $this->nama_opd,
            'akun_jenis' => $this->akun_jenis,
            'nama_akun' => $this->nama_akun,
            'anggaran' => (string) $this->anggaran,
            'realisasi' => (string) $this->realisasi,
            'publication_status' => $this->publication_status->value,
            'revises_id' => $this->revises_id,
            'source_id' => $this->source_id,
            'import_job_id' => $this->import_job_id,
            'source_original_data' => $this->source_original_data,
            'created_by' => $this->created_by,
            'reviewed_by' => $this->reviewed_by,
            'published_at' => $this->published_at?->toDateTimeString(),
            'source' => $this->whenLoaded('sumberData', fn (): ?array => $this->sumberData?->only([
                'id', 'name', 'source_type', 'source_url', 'license_info',
            ])),
            'import_job' => $this->whenLoaded('pekerjaanImpor', fn (): ?array => $this->pekerjaanImpor?->only([
                'id', 'target_type', 'original_filename', 'status', 'total_rows', 'accepted_rows', 'rejected_rows',
            ])),
        ];
    }
}
