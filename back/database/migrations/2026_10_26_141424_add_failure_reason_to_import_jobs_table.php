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
        Schema::table('import_jobs', function (Blueprint $table) {
            $table->unsignedInteger('processed_rows')->default(0)->after('total_rows');
            $table->text('failure_reason')->nullable()->after('error_report_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('import_jobs', function (Blueprint $table) {
            $table->dropColumn(['processed_rows', 'failure_reason']);
        });
    }
};
