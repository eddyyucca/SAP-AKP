<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

class DemoData extends Command
{
    protected $signature = 'ssap:demo {--fresh : Hapus dulu seluruh laporan, tindakan, dan jam kerja yang ada}';

    protected $description = 'Isi data REKAAN (14 bulan) untuk mencoba dashboard dan laporan. Hanya untuk lokal/pengembangan.';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Data demo tidak boleh dibuat di produksi.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            DemoSeeder::wipe();
            $this->warn('Data lama dihapus.');
        }

        (new DemoSeeder)->run();

        $this->info('Data demo dibuat. Buka dashboard SSAP Safety untuk melihatnya.');

        return self::SUCCESS;
    }
}
