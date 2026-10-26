<?php

namespace Database\Factories\Apbd;

use App\Enums\Wilayah\PublicationStatus;
use App\Models\Apbd\Apbd;
use App\Models\Pengguna;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Apbd>
 */
class ApbdFactory extends Factory
{
    protected $model = Apbd::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'periode_data' => '202403',
            'tanggal' => '2024-03-31',
            'kode_opd' => '001.02',
            'nama_opd' => fake()->company(),
            'akun_jenis' => '5',
            'nama_akun' => fake()->words(3, true),
            'anggaran' => '1250000000.00',
            'realisasi' => '985000000.00',
            'source_fingerprint' => hash('sha256', fake()->uuid()),
            'publication_status' => PublicationStatus::Draft,
            'created_by' => Pengguna::factory(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'publication_status' => PublicationStatus::Published,
            'published_at' => now(),
        ]);
    }
}
