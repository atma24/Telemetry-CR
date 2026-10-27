<?php

namespace Database\Seeders;

use App\Models\Pengguna;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PenggunaSeeder extends Seeder
{
    public function run(): void
    {
        $adminPassword = config('seed.admin_password');
        $citizenPassword = config('seed.citizen_password');

        if (app()->environment(['local', 'testing'])) {
            $adminPassword ??= 'password';
            $citizenPassword ??= 'password';
        }

        if (is_string($adminPassword) && $adminPassword !== '') {
            $this->seedAccount(
                'admin@example.com',
                'Admin TeraPublic',
                'admin',
                $adminPassword,
            );
        }

        if (is_string($citizenPassword) && $citizenPassword !== '') {
            $this->seedAccount(
                'warga@example.com',
                'Warga TeraPublic',
                'citizen',
                $citizenPassword,
            );
        }
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
