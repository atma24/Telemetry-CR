<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $duplicate = DB::table('facilities')
            ->select('external_key')
            ->whereNotNull('external_key')
            ->groupBy('external_key')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate !== null) {
            throw new RuntimeException('Cannot add the facility external-key constraint until duplicate keys are reviewed and resolved.');
        }

        DB::statement(
            'CREATE UNIQUE INDEX facilities_external_key_unique '
            .'ON facilities (external_key) WHERE external_key IS NOT NULL'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS facilities_external_key_unique');
    }
};
