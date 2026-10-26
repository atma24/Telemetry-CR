<?php

namespace App\Http\Resources\Apbd;

use App\Models\Apbd\Apbd;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/** @mixin Apbd */
class ApbdResource extends JsonResource
{
    public static $wrap = null;

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
            'source' => $this->whenLoaded('sumberData', fn (): ?array => $this->sumberData?->only([
                'id', 'name', 'source_type', 'source_url', 'license_info',
            ])),
            'published_at' => $this->published_at?->toDateTimeString(),
        ];
    }
}
