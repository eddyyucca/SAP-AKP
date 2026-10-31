<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Konsep baru: pelapor = PIC hazard. Perbaikan dikerjakan pihak penanggung jawab secara manual (di luar sistem);
 * pelapor mencatat tindakan perbaikannya dan itulah yang menutup laporan. Safety hanya memantau.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hazards', function (Blueprint $table) {
            $table->string('responsible', 160)->nullable()->after('area_detail');   // pihak yang memperbaiki (bebas diisi)
            $table->date('reminded_on')->nullable();                                // pengingat harian ke pelapor
        });

        // Hazard lama: PIC yang belum ada diisi pelapornya.
        DB::table('hazards')->whereNull('pic_name')->update([
            'pic_nik' => DB::raw('reporter_nik'), 'pic_name' => DB::raw('reporter_name'),
            'pic_department' => DB::raw('reporter_department'), 'pic_email' => DB::raw('reporter_email'),
        ]);
    }

    public function down(): void
    {
        Schema::table('hazards', fn (Blueprint $table) => $table->dropColumn(['responsible', 'reminded_on']));
    }
};
