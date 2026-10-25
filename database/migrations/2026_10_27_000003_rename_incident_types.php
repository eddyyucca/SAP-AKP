<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Penamaan jenis insiden mengikuti format laporan kecelakaan (KEPMEN ESDM 1827/2018 dan ANSI):
 *  - "lost_time" (LTI) menjadi "serious_injury" (Cidera Berat)
 *  - "vehicle" (kendaraan/alat berat) digabung ke "property_damage"
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('incidents')->where('type', 'lost_time')->update(['type' => 'serious_injury']);
        DB::table('incidents')->where('type', 'vehicle')->update(['type' => 'property_damage']);
    }

    public function down(): void
    {
        DB::table('incidents')->where('type', 'serious_injury')->update(['type' => 'lost_time']);
    }
};
