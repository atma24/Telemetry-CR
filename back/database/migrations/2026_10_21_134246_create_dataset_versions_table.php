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
        Schema::create('dataset_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('dataset_id');
            $table->unsignedInteger('version_no');
            $table->uuid('base_version_id')->nullable();
            $table->uuid('source_id')->nullable();
            $table->uuid('import_job_id')->nullable();
            $table->enum('publication_status', ['draft', 'validating', 'published', 'superseded', 'archived'])
                ->default('draft');
            $table->jsonb('schema_definition')->nullable();
            $table->text('original_file_path')->nullable();
            $table->uuid('created_by');
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['dataset_id', 'version_no']);
            $table->index('base_version_id');
            $table->index('publication_status');

            $table->foreign('dataset_id')->references('id')->on('datasets')->cascadeOnDelete();
            $table->foreign('source_id')->references('id')->on('data_sources')->nullOnDelete();
            $table->foreign('import_job_id')->references('id')->on('import_jobs')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('dataset_versions', function (Blueprint $table) {
            $table->foreign('base_version_id')->references('id')->on('dataset_versions')->nullOnDelete();
        });

        DB::statement(
            'CREATE UNIQUE INDEX dataset_versions_one_published_per_dataset '
            .'ON dataset_versions (dataset_id) WHERE publication_status = \'published\''
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS dataset_versions_one_published_per_dataset');

        Schema::dropIfExists('dataset_versions');
    }
};
