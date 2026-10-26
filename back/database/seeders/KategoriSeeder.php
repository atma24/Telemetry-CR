<?php

namespace Database\Seeders;

use App\Models\Kategori;
use Illuminate\Database\Seeder;

class KategoriSeeder extends Seeder
{
    /** @var array<int, array{name: string, slug: string}> */
    private const KATEGORI = [
        ['name' => 'Statistik Wilayah', 'slug' => 'statistik-wilayah'],
        ['name' => 'Fasilitas Publik', 'slug' => 'fasilitas-publik'],
        ['name' => 'Infrastruktur dan Pembangunan', 'slug' => 'infrastruktur-pembangunan'],
        ['name' => 'Transportasi', 'slug' => 'transportasi'],
        ['name' => 'Lingkungan', 'slug' => 'lingkungan'],
        ['name' => 'Pendidikan', 'slug' => 'pendidikan'],
        ['name' => 'Ekonomi', 'slug' => 'ekonomi'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::KATEGORI as $kategori) {
            Kategori::query()->firstOrCreate(
                ['slug' => $kategori['slug']],
                ['name' => $kategori['name']],
            );
        }
    }
}
