<?php

use App\Models\Pengguna;

it('returns 404 for retired public and administrative project endpoints', function () {
    $this->getJson('/api/projects')->assertNotFound();
    $this->getJson('/api/projects/00000000-0000-7000-8000-000000000001')->assertNotFound();

    $admin = Pengguna::factory()->admin()->create();

    $this->actingAs($admin)->getJson('/api/admin/projects')->assertNotFound();
    $this->actingAs($admin)->postJson('/api/admin/projects')->assertNotFound();
});
