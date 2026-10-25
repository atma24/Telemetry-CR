<?php

namespace Database\Seeders;

use App\Models\Pengguna;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Pengguna::factory(10)->create();

        Pengguna::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
