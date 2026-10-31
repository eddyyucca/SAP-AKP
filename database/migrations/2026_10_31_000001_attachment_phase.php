<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Foto hazard dibedakan: "before" = kondisi saat dilaporkan, "after" = bukti perbaikan yang dicatat pelapor. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->string('phase', 10)->nullable()->after('mime');
        });

        // Lampiran hazard yang sudah ada dianggap foto sebelum.
        DB::table('attachments')->where('attachable_type', 'hazard')->update(['phase' => 'before']);
    }

    public function down(): void
    {
        Schema::table('attachments', fn (Blueprint $table) => $table->dropColumn('phase'));
    }
};
