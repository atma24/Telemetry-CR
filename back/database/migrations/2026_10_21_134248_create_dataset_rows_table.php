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
        Schema::create('dataset_rows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('dataset_version_id');
            $table->unsignedInteger('row_number');
            $table->jsonb('row_data');
            $table->string('validation_status');
            $table->jsonb('validation_errors')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['dataset_version_id', 'row_number']);

            $table->foreign('dataset_version_id')->references('id')->on('dataset_versions')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dataset_rows');
    }
};
