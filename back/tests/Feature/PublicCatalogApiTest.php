<?php

it('serves the new public catalog and transparency endpoints', function () {
    $this->getJson('/api/facilities')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->getJson('/api/projects')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->getJson('/api/datasets')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->getJson('/api/dashboard')
        ->assertOk()
        ->assertExactJson([
            'facilities' => 0,
            'projects' => 0,
            'datasets' => 0,
            'feedback_by_status' => [],
        ]);
});
