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
        Schema::create('feedbacks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('facility_id')->nullable();
            $table->uuid('project_id')->nullable();
            $table->uuid('region_id')->nullable();
            $table->uuid('dataset_id')->nullable();
            $table->string('subject');
            $table->text('message');
            $table->enum('status', ['submitted', 'in_review', 'in_progress', 'resolved', 'rejected'])
                ->default('submitted');
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('facility_id');
            $table->index('project_id');
            $table->index('region_id');
            $table->index('dataset_id');
            $table->index('status');

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('facility_id')->references('id')->on('facilities')->cascadeOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('region_id')->references('id')->on('regions')->cascadeOnDelete();
            $table->foreign('dataset_id')->references('id')->on('datasets')->cascadeOnDelete();
        });

        DB::statement(
            'ALTER TABLE feedbacks ADD CONSTRAINT feedbacks_at_most_one_target '
            .'CHECK (num_nonnulls(facility_id, project_id, region_id, dataset_id) <= 1)'
        );

        DB::statement(
            'ALTER TABLE feedbacks ADD CONSTRAINT feedbacks_rejection_reason_required '
            .'CHECK (status <> \'rejected\' OR rejection_reason IS NOT NULL)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedbacks');
    }
};
