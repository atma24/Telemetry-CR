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
        Schema::create('project_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->unsignedInteger('version_no');
            $table->string('name');
            $table->uuid('category_id');
            $table->string('agency')->nullable();
            $table->date('start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->decimal('progress_percentage', 5, 2)->nullable();
            $table->string('project_status')->nullable();
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

            $table->unique(['project_id', 'version_no']);
            $table->index('category_id');
            $table->index('publication_status');

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->restrictOnDelete();
            $table->foreign('source_id')->references('id')->on('data_sources')->nullOnDelete();
            $table->foreign('import_job_id')->references('id')->on('import_jobs')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
        });

        DB::statement(
            'CREATE UNIQUE INDEX project_versions_one_published_per_project '
            .'ON project_versions (project_id) WHERE publication_status = \'published\''
        );

        DB::statement(
            'ALTER TABLE project_versions ADD CONSTRAINT project_versions_progress_range '
            .'CHECK (progress_percentage IS NULL OR (progress_percentage >= 0 AND progress_percentage <= 100))'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS project_versions_one_published_per_project');

        Schema::dropIfExists('project_versions');
    }
};
