<?php

namespace App\Console\Commands;

use App\Services\HrisEmployees;
use Illuminate\Console\Command;

class SyncHris extends Command
{
    protected $signature = 'ssap:sync-hris';

    protected $description = 'Ambil ulang daftar karyawan dari HRIS (API baca-saja) ke cache pencarian';

    public function handle(HrisEmployees $hris): int
    {
        if (! HrisEmployees::configured()) {
            $this->warn('HRIS_API_URL / HRIS_API_TOKEN belum diisi; sinkron dilewati.');

            return self::SUCCESS;
        }

        try {
            $count = $hris->refresh();
        } catch (\Throwable $e) {
            $this->error('Sinkron HRIS gagal: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Sinkron HRIS selesai: {$count} karyawan.");

        return self::SUCCESS;
    }
}
