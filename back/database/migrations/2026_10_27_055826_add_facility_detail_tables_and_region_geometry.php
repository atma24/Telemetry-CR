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
        Schema::table('facility_versions', function (Blueprint $table): void {
            $table->dropForeign(['region_id']);
            $table->uuid('region_id')->nullable()->change();
            $table->foreign('region_id')->references('id')->on('regions')->restrictOnDelete();
        });

        Schema::table('region_versions', function (Blueprint $table): void {
            $table->jsonb('geometry')->nullable();
            $table->decimal('centroid_latitude', 10, 7)->nullable();
            $table->decimal('centroid_longitude', 10, 7)->nullable();
        });

        Schema::create('schools', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('facility_version_id')->unique();
            $table->string('periode_data')->nullable();
            $table->string('npsn')->nullable();
            $table->string('kelurahan_sumber')->nullable();
            $table->string('kecamatan_sumber')->nullable();
            $table->integer('jumlah_siswa')->nullable();
            $table->integer('jumlah_guru')->nullable();
            $table->string('kepala_sekolah')->nullable();
            $table->string('telp_sekolah')->nullable();
            $table->string('akreditasi')->nullable();
            $table->timestamps();
            $table->foreign('facility_version_id')->references('id')->on('facility_versions')->cascadeOnDelete();
        });

        Schema::create('market_locations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('facility_version_id')->unique();
            $table->string('periode_data')->nullable();
            $table->string('wilayah_sumber')->nullable();
            $table->string('kecamatan_sumber')->nullable();
            $table->string('kelurahan_sumber')->nullable();
            $table->string('kode_pos')->nullable();
            $table->string('telepon')->nullable();
            $table->timestamps();
            $table->foreign('facility_version_id')->references('id')->on('facility_versions')->cascadeOnDelete();
        });

        DB::statement(
            'ALTER TABLE region_versions ADD CONSTRAINT region_versions_centroid_latitude_range '
            .'CHECK (centroid_latitude IS NULL OR (centroid_latitude >= -90 AND centroid_latitude <= 90))'
        );
        DB::statement(
            'ALTER TABLE region_versions ADD CONSTRAINT region_versions_centroid_longitude_range '
            .'CHECK (centroid_longitude IS NULL OR (centroid_longitude >= -180 AND centroid_longitude <= 180))'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE region_versions DROP CONSTRAINT IF EXISTS region_versions_centroid_latitude_range');
        DB::statement('ALTER TABLE region_versions DROP CONSTRAINT IF EXISTS region_versions_centroid_longitude_range');
        Schema::dropIfExists('market_locations');
        Schema::dropIfExists('schools');
        Schema::table('region_versions', function (Blueprint $table): void {
            $table->dropColumn(['geometry', 'centroid_latitude', 'centroid_longitude']);
        });
        Schema::table('facility_versions', function (Blueprint $table): void {
            $table->dropForeign(['region_id']);
            $table->uuid('region_id')->nullable(false)->change();
            $table->foreign('region_id')->references('id')->on('regions')->restrictOnDelete();
        });
    }
};
