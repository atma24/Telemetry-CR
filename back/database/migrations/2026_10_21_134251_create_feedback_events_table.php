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
        Schema::create('feedback_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('feedback_id');
            $table->uuid('actor_user_id');
            $table->string('old_status')->nullable();
            $table->string('new_status');
            $table->text('response_message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('feedback_id');
            $table->index('actor_user_id');

            $table->foreign('feedback_id')->references('id')->on('feedbacks')->cascadeOnDelete();
            $table->foreign('actor_user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback_events');
    }
};
