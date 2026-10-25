<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('project_version_regions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_version_id');
            $table->uuid('region_id');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['project_version_id', 'region_id']);
            $table->index('region_id');

            $table->foreign('project_version_id')->references('id')->on('project_versions')->cascadeOnDelete();
            $table->foreign('region_id')->references('id')->on('regions')->cascadeOnDelete();
        });

        DB::statement(
            'CREATE UNIQUE INDEX project_version_regions_one_primary_per_version '
            .'ON project_version_regions (project_version_id) WHERE is_primary = true'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS project_version_regions_one_primary_per_version');

        Schema::dropIfExists('project_version_regions');
    }
};
