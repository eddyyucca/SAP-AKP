<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('category', 30)->default('other');   // kategori hazard bila ada temuan
            $table->string('description', 255)->nullable();
            $table->unsignedSmallInteger('target_per_month')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('inspection_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('inspection_templates')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('question', 255);
        });

        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->string('number', 24)->unique();
            $table->foreignId('template_id')->nullable()->constrained('inspection_templates')->nullOnDelete();
            $table->string('template_name', 120);
            $table->string('category', 30)->default('other');
            $table->foreignId('location_id')->constrained('locations');
            $table->string('area_detail', 160)->nullable();
            $table->dateTime('inspected_at')->index();

            $table->string('inspector_nik', 30)->nullable()->index();
            $table->string('inspector_name', 120);
            $table->string('inspector_department', 120)->nullable();
            $table->string('inspector_email', 160)->nullable();
            $table->string('created_by_id', 30)->nullable()->index();

            $table->unsignedSmallInteger('total_items')->default(0);
            $table->unsignedSmallInteger('ok_count')->default(0);
            $table->unsignedSmallInteger('nok_count')->default(0);
            $table->unsignedSmallInteger('na_count')->default(0);
            $table->decimal('score', 5, 1)->nullable();
            $table->text('notes')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('inspection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained('inspections')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('question', 255);
            $table->string('result', 4);                    // ok | nok | na
            $table->text('note')->nullable();
            $table->unsignedBigInteger('hazard_id')->nullable()->index();
        });

        // Template bawaan (umum); Administrator/HSE dapat mengubah, menambah, atau menonaktifkannya.
        $now = now();
        $defaults = [
            ['Inspeksi APD & Perilaku Kerja', 'ppe', 8, [
                'Pekerja memakai helm, sepatu safety, dan rompi reflektif', 'Kacamata/face shield dipakai pada pekerjaan berisiko percikan', 'Sarung tangan sesuai jenis pekerjaan', 'Pelindung telinga dipakai di area bising', 'APD dalam kondisi baik dan tidak rusak', 'Pekerja memahami JSA/instruksi kerja pekerjaannya', 'Tidak ada pekerja bekerja sendirian pada pekerjaan berisiko', 'Rambu wajib APD terpasang jelas',
            ]],
            ['Inspeksi Unit & Kendaraan', 'vehicle', 4, [
                'Pemeriksaan harian (P2H) unit sudah diisi', 'Rem, klakson, lampu, dan alarm mundur berfungsi', 'Sabuk pengaman berfungsi dan dipakai', 'Kaca, spion, dan wiper dalam kondisi baik', 'APAR unit tersedia, tersegel, dan masa uji berlaku', 'Tidak ada kebocoran oli/BBM/hidrolik', 'Kondisi ban sesuai standar', 'Pengemudi memiliki izin mengemudi (SIMPER) yang berlaku',
            ]],
            ['Inspeksi Workshop & Housekeeping', 'housekeeping', 4, [
                'Jalur jalan dan jalur evakuasi bebas hambatan', 'Material dan perkakas tertata pada tempatnya', 'Tidak ada tumpahan oli atau genangan di lantai', 'Tempat sampah terpisah dan tidak meluap', 'Penerangan area kerja memadai', 'Mesin gerinda/bor memiliki pelindung (guard) lengkap', 'Kabel listrik tertata dan tidak terkelupas', 'Rak penyimpanan kokoh dan tidak kelebihan beban',
            ]],
            ['Inspeksi APAR & Peralatan Darurat', 'fire', 2, [
                'APAR tersedia pada titik yang ditentukan dan mudah dijangkau', 'Segel utuh dan indikator tekanan pada area hijau', 'Masa uji/isi ulang APAR masih berlaku', 'Kotak P3K lengkap dan tidak kedaluwarsa', 'Eyewash/safety shower berfungsi', 'Titik kumpul dan rambu evakuasi terlihat jelas', 'Jalur evakuasi tidak terhalang', 'Spill kit tersedia dan lengkap',
            ]],
            ['Inspeksi Area Kerja Berisiko (Ketinggian & Listrik)', 'height', 2, [
                'Izin kerja (permit) tersedia untuk pekerjaan berisiko', 'Full body harness dan lifeline dipakai di ketinggian', 'Scaffolding/tangga kokoh dan terpasang tag inspeksi', 'Area bawah pekerjaan diberi pembatas', 'Lock Out Tag Out (LOTO) diterapkan sebelum perawatan', 'Panel listrik tertutup dan berlabel', 'Peralatan listrik sesuai dan tidak rusak', 'Tersedia pengawas yang kompeten di lokasi',
            ]],
        ];

        foreach ($defaults as [$name, $category, $target, $items]) {
            $id = DB::table('inspection_templates')->insertGetId([
                'name' => $name, 'category' => $category, 'target_per_month' => $target, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach ($items as $i => $question) {
                DB::table('inspection_template_items')->insert(['template_id' => $id, 'position' => $i + 1, 'question' => $question]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_items');
        Schema::dropIfExists('inspections');
        Schema::dropIfExists('inspection_template_items');
        Schema::dropIfExists('inspection_templates');
    }
};
