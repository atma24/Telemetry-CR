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
        Schema::create('facility_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('facility_id');
            $table->unsignedInteger('version_no');
            $table->string('name');
            $table->uuid('category_id');
            $table->uuid('region_id');
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->jsonb('attributes')->nullable();
            $table->uuid('source_id')->nullable();
            $table->uuid('import_job_id')->nullable();
            $table->jsonb('source_original_data')->nullable();
            $table->enum('publication_status', ['draft', 'validating', 'published', 'superseded', 'archived'])
                ->default('draft');
            $table->uuid('created_by');
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['facility_id', 'version_no']);
            $table->index('category_id');
            $table->index('region_id');
            $table->index('publication_status');

            $table->foreign('facility_id')->references('id')->on('facilities')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->restrictOnDelete();
            $table->foreign('region_id')->references('id')->on('regions')->restrictOnDelete();
            $table->foreign('source_id')->references('id')->on('data_sources')->nullOnDelete();
            $table->foreign('import_job_id')->references('id')->on('import_jobs')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
        });

        DB::statement(
            'CREATE UNIQUE INDEX facility_versions_one_published_per_facility '
            .'ON facility_versions (facility_id) WHERE publication_status = \'published\''
        );

        DB::statement(
            'ALTER TABLE facility_versions ADD CONSTRAINT facility_versions_latitude_range '
            .'CHECK (latitude IS NULL OR (latitude >= -90 AND latitude <= 90))'
        );

        DB::statement(
            'ALTER TABLE facility_versions ADD CONSTRAINT facility_versions_longitude_range '
            .'CHECK (longitude IS NULL OR (longitude >= -180 AND longitude <= 180))'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS facility_versions_one_published_per_facility');

        Schema::dropIfExists('facility_versions');
    }
};
