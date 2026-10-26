<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apbd', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('periode_data', 6);
            $table->date('tanggal');
            $table->string('kode_opd', 100);
            $table->string('nama_opd');
            $table->string('akun_jenis', 100);
            $table->string('nama_akun');
            $table->decimal('anggaran', 38, 2);
            $table->decimal('realisasi', 38, 2);
            $table->string('source_fingerprint', 64)->index();
            $table->uuid('revises_id')->nullable();
            $table->uuid('source_id')->nullable();
            $table->uuid('import_job_id')->nullable();
            $table->jsonb('source_original_data')->nullable();
            $table->enum('publication_status', ['draft', 'validating', 'published', 'superseded', 'archived'])
                ->default('draft');
            $table->uuid('created_by');
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index('periode_data');
            $table->index('kode_opd');
            $table->index('akun_jenis');
            $table->index(['publication_status', 'periode_data']);

            $table->foreign('source_id')->references('id')->on('data_sources')->nullOnDelete();
            $table->foreign('import_job_id')->references('id')->on('import_jobs')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('apbd', function (Blueprint $table): void {
            $table->foreign('revises_id')->references('id')->on('apbd')->nullOnDelete();
        });

        DB::statement('ALTER TABLE apbd ADD CONSTRAINT apbd_amounts_nonnegative CHECK (anggaran >= 0 AND realisasi >= 0)');
        DB::statement("ALTER TABLE apbd ADD CONSTRAINT apbd_period_format CHECK (periode_data ~ '^[0-9]{6}$' AND substring(periode_data, 5, 2)::integer BETWEEN 1 AND 12)");

        if (Schema::hasTable('import_jobs') && ! Schema::hasColumn('import_jobs', 'error_report_path')) {
            Schema::table('import_jobs', function (Blueprint $table): void {
                $table->text('error_report_path')->nullable();
            });
        }

        if (Schema::hasTable('feedbacks')) {
            DB::statement('ALTER TABLE feedbacks DROP CONSTRAINT IF EXISTS feedbacks_at_most_one_target');

            if (Schema::hasColumn('feedbacks', 'project_id')) {
                DB::table('feedbacks')->whereNotNull('project_id')->update(['project_id' => null]);

                Schema::table('feedbacks', function (Blueprint $table): void {
                    $table->dropForeign(['project_id']);
                    $table->dropIndex('feedbacks_project_id_index');
                    $table->dropColumn('project_id');
                });
            }

            if (! Schema::hasColumn('feedbacks', 'apbd_id')) {
                Schema::table('feedbacks', function (Blueprint $table): void {
                    $table->uuid('apbd_id')->nullable()->index();
                    $table->foreign('apbd_id')->references('id')->on('apbd')->nullOnDelete();
                });
            }

            DB::statement(
                'ALTER TABLE feedbacks ADD CONSTRAINT feedbacks_at_most_one_target '
                .'CHECK (num_nonnulls(facility_id, apbd_id, region_id, dataset_id) <= 1)'
            );
        }

        Schema::dropIfExists('project_version_regions');
        Schema::dropIfExists('project_versions');
        Schema::dropIfExists('projects');
    }

    public function down(): void
    {
        throw new RuntimeException(
            'This migration removes project data and cannot be safely rolled back. Restore from a verified backup or use a forward migration.'
        );
    }
};
