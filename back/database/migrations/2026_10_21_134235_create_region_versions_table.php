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
        Schema::create('region_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('region_id');
            $table->unsignedInteger('version_no');
            $table->string('code');
            $table->string('name');
            $table->string('level');
            $table->uuid('parent_region_id')->nullable();
            $table->uuid('source_id')->nullable();
            $table->uuid('import_job_id')->nullable();
            $table->jsonb('source_original_data')->nullable();
            $table->enum('publication_status', ['draft', 'validating', 'published', 'superseded', 'archived'])
                ->default('draft');
            $table->uuid('created_by');
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['region_id', 'version_no']);
            $table->index('code');
            $table->index('parent_region_id');
            $table->index('publication_status');

            $table->foreign('region_id')->references('id')->on('regions')->cascadeOnDelete();
            $table->foreign('parent_region_id')->references('id')->on('regions')->nullOnDelete();
            $table->foreign('source_id')->references('id')->on('data_sources')->nullOnDelete();
            $table->foreign('import_job_id')->references('id')->on('import_jobs')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
        });

        DB::statement(
            'CREATE UNIQUE INDEX region_versions_one_published_per_region '
            .'ON region_versions (region_id) WHERE publication_status = \'published\''
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS region_versions_one_published_per_region');

        Schema::dropIfExists('region_versions');
    }
};
