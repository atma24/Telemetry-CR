<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('import_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('target_type');
            $table->uuid('source_id')->nullable();
            $table->string('original_filename')->nullable();
            $table->text('file_path')->nullable();
            $table->enum('status', ['uploaded', 'processing', 'completed', 'completed_with_errors', 'failed'])
                ->default('uploaded');
            $table->unsignedInteger('total_rows')->nullable();
            $table->unsignedInteger('accepted_rows')->nullable();
            $table->unsignedInteger('rejected_rows')->nullable();
            $table->uuid('initiated_by');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('target_type');

            $table->foreign('source_id')->references('id')->on('data_sources')->nullOnDelete();
            $table->foreign('initiated_by')->references('id')->on('users')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_jobs');
    }
};
