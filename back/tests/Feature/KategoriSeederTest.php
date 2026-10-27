<?php

use App\Models\Kategori;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\KategoriSeeder;

it('seeds the documented topic categories once without inventing sources or facilities', function () {
    $this->seed(DatabaseSeeder::class);

    $this->assertDatabaseCount('categories', 9);
    $this->assertDatabaseHas('categories', ['slug' => 'sekolah', 'name' => 'Sekolah']);
    $this->assertDatabaseHas('categories', ['slug' => 'pasar', 'name' => 'Pasar']);
    $this->assertDatabaseCount('data_sources', 0);
    $this->assertDatabaseCount('facilities', 0);
    $this->assertDatabaseCount('facility_versions', 0);

    $category = Kategori::query()->where('slug', 'fasilitas-publik')->firstOrFail();
    $category->update(['name' => 'Fasilitas publik — disesuaikan Admin']);

    $this->seed(KategoriSeeder::class);

    $this->assertDatabaseCount('categories', 9);
    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'slug' => 'fasilitas-publik',
        'name' => 'Fasilitas publik — disesuaikan Admin',
    ]);
});
