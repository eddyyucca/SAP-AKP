<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Jenis hazard disederhanakan menjadi TTA (Tindakan Tidak Aman) dan KTA (Kondisi Tidak Aman). */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('hazards')->where('category', 'unsafe_act')->update(['category' => 'tta']);
        DB::table('hazards')->whereNotIn('category', ['tta'])->update(['category' => 'kta']);
        DB::table('inspection_templates')->where('category', 'unsafe_condition')->update(['category' => 'other']);
        DB::table('inspection_templates')->where('category', 'unsafe_act')->update(['category' => 'other']);
    }

    public function down(): void
    {
        // Pemetaan tidak dapat dikembalikan: kategori lama sudah digabung.
    }
};
