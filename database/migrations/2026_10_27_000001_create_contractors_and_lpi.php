<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kontraktor yang bekerja di area IUP (Izin Usaha Pertambangan) atau IUJP (Izin Usaha Jasa Pertambangan).
        Schema::create('contractors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('area_type', 4);                 // iup | iujp
            $table->string('permit_no', 80)->nullable();
            $table->string('pic_name', 120)->nullable();
            $table->string('pic_email', 160)->nullable();
            $table->string('pic_phone', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('area_type');
        });

        Schema::table('incidents', function (Blueprint $table) {
            $table->foreignId('contractor_id')->nullable()->after('location_id')->constrained('contractors')->nullOnDelete();
            // LPI = Laporan Penyelidikan Insiden dari kontraktor. null = tidak diwajibkan.
            $table->string('lpi_status', 12)->nullable()->index();     // pending | submitted | revision | accepted
            $table->date('lpi_due')->nullable()->index();
            $table->string('lpi_number', 80)->nullable();
            $table->date('lpi_submitted_at')->nullable();
            $table->dateTime('lpi_reviewed_at')->nullable();
            $table->string('lpi_reviewed_by', 120)->nullable();
            $table->text('lpi_note')->nullable();
            $table->unsignedSmallInteger('lpi_revisions')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contractor_id');
            $table->dropColumn(['lpi_status', 'lpi_due', 'lpi_number', 'lpi_submitted_at', 'lpi_reviewed_at', 'lpi_reviewed_by', 'lpi_note', 'lpi_revisions']);
        });
        Schema::dropIfExists('contractors');
    }
};
