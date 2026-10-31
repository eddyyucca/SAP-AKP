<?php

namespace App\Providers;

use App\Models\Hazard;
use App\Models\Inspection;
use Illuminate\Pagination\Paginator;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Nama pendek tersimpan di kolom *_type (bukan nama kelas PHP), supaya data tidak terikat pada struktur kode.
        Relation::morphMap([
            'hazard' => Hazard::class,
            'inspection' => Inspection::class,
        ]);

        Paginator::useBootstrapFive();

        Carbon::setLocale('id');
    }
}
