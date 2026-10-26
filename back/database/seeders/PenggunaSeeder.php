<?php

namespace Database\Seeders;

use App\Models\Pengguna;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PenggunaSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAccount(
            'admin@example.com',
            'Admin TeraPublic',
            'admin',
            (string) env('SEED_ADMIN_PASSWORD', 'password'),
        );
        $this->seedAccount(
            'warga@example.com',
            'Warga TeraPublic',
            'citizen',
            (string) env('SEED_CITIZEN_PASSWORD', 'password'),
        );
    }

    private function seedAccount(string $email, string $name, string $role, string $password): void
    {
        $user = Pengguna::query()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $name,
            'password' => Hash::make($password),
            'role' => $role,
            'account_status' => 'active',
            'email_verified_at' => now(),
        ])->save();
    }
}
