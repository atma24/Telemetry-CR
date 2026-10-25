<?php

use App\Models\Pengguna;
use Illuminate\Support\Facades\Gate;

it('requires authentication to access the current user endpoint', function () {
    $this->getJson('/api/user')->assertUnauthorized();
});

it('returns a safe current user resource for an authenticated citizen', function () {
    $user = Pengguna::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.role', 'citizen')
        ->assertJsonMissingPath('data.password');
});

it('grants the admin ability only to active admins', function () {
    $admin = Pengguna::factory()->admin()->create();
    $citizen = Pengguna::factory()->create();
    $inactiveAdmin = Pengguna::factory()->admin()->create(['account_status' => 'suspended']);

    expect(Gate::forUser($admin)->allows('admin'))->toBeTrue();
    expect(Gate::forUser($citizen)->allows('admin'))->toBeFalse();
    expect(Gate::forUser($inactiveAdmin)->allows('admin'))->toBeFalse();
});

it('allows the configured frontend origin to use credentialed API requests', function () {
    $this->withHeader('Origin', 'http://localhost:3000')
        ->getJson('/api/health')
        ->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
        ->assertHeader('Access-Control-Allow-Credentials', 'true');
});
