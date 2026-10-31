<?php

use App\Http\Controllers\ActionController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\ContractorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\HazardController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\InspectionController;
use App\Http\Controllers\InspectionTemplateController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\LpiController;
use App\Http\Controllers\ManHourController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StandardController;
use Illuminate\Support\Facades\Route;

// Semua halaman sudah wajib login Portal (config/sso.php, protect_all). Level dicek per grup di bawah
// (dan sekali lagi di controller lewat App\Support\Access).
Route::redirect('/', '/ssap')->name('home');

Route::middleware(['portal.role:admin,hse,manager,user', 'throttle:120,1'])->prefix('ssap')->name('ssap.')->group(function () {
    Route::get('/', [DashboardController::class, 'home'])->name('home');
    Route::get('standar', StandardController::class)->name('standard');
    Route::get('karyawan', [EmployeeController::class, 'search'])->name('employees.search');

    // Laporan insiden: semua pengguna melapor; melihat dibatasi per laporan (Incident::visibleTo).
    Route::get('insiden', [IncidentController::class, 'index'])->name('incidents.index');
    Route::get('insiden/baru', [IncidentController::class, 'create'])->name('incidents.create');
    Route::post('insiden', [IncidentController::class, 'store'])->middleware('throttle:30,1')->name('incidents.store');
    Route::get('insiden/{incident}', [IncidentController::class, 'show'])->whereNumber('incident')->name('incidents.show');
    Route::get('insiden/{incident}/cetak', [IncidentController::class, 'print'])->whereNumber('incident')->name('incidents.print');
    Route::get('insiden/{incident}/ubah', [IncidentController::class, 'edit'])->whereNumber('incident')->name('incidents.edit');
    Route::put('insiden/{incident}', [IncidentController::class, 'update'])->whereNumber('incident')->name('incidents.update');

    // Hazard report
    Route::get('hazard', [HazardController::class, 'index'])->name('hazards.index');
    Route::get('hazard/baru', [HazardController::class, 'create'])->name('hazards.create');
    Route::post('hazard', [HazardController::class, 'store'])->middleware('throttle:30,1')->name('hazards.store');
    Route::get('hazard/{hazard}', [HazardController::class, 'show'])->whereNumber('hazard')->name('hazards.show');
    Route::get('hazard/{hazard}/ubah', [HazardController::class, 'edit'])->whereNumber('hazard')->name('hazards.edit');
    Route::put('hazard/{hazard}', [HazardController::class, 'update'])->whereNumber('hazard')->name('hazards.update');

    // Inspeksi rutin: siapa pun yang berwenang melakukan inspeksi mengisi checklist; melihat dibatasi per laporan.
    Route::get('inspeksi', [InspectionController::class, 'index'])->name('inspections.index');
    Route::get('inspeksi/baru', [InspectionController::class, 'create'])->name('inspections.create');
    Route::post('inspeksi', [InspectionController::class, 'store'])->middleware('throttle:30,1')->name('inspections.store');
    Route::get('inspeksi/{inspection}', [InspectionController::class, 'show'])->whereNumber('inspection')->name('inspections.show');

    // Tindakan korektif: PIC memperbarui progres miliknya; penetapan dan verifikasi khusus HSE (di bawah).
    Route::get('tindakan', [ActionController::class, 'index'])->name('actions.index');
    Route::put('tindakan/{action}/progres', [ActionController::class, 'progress'])->whereNumber('action')->name('actions.progress');

    Route::get('lampiran/{attachment}', [AttachmentController::class, 'show'])->whereNumber('attachment')->name('attachments.show');

    // Petugas HSE & Administrator: menangani laporan.
    Route::middleware('portal.role:admin,hse')->group(function () {
        Route::put('insiden/{incident}/investigasi', [IncidentController::class, 'investigate'])->whereNumber('incident')->name('incidents.investigate');
        Route::post('insiden/{incident}/status', [IncidentController::class, 'transition'])->whereNumber('incident')->name('incidents.transition');

        Route::post('hazard/{hazard}/tugaskan', [HazardController::class, 'assign'])->whereNumber('hazard')->name('hazards.assign');
        Route::post('hazard/{hazard}/status', [HazardController::class, 'resolve'])->whereNumber('hazard')->name('hazards.resolve');

        Route::post('tindakan', [ActionController::class, 'store'])->name('actions.store');
        Route::post('tindakan/{action}/verifikasi', [ActionController::class, 'verify'])->whereNumber('action')->name('actions.verify');
        Route::delete('tindakan/{action}', [ActionController::class, 'destroy'])->whereNumber('action')->name('actions.destroy');
        Route::delete('lampiran/{attachment}', [AttachmentController::class, 'destroy'])->whereNumber('attachment')->name('attachments.destroy');

        Route::post('insiden/{incident}/lpi', [LpiController::class, 'update'])->whereNumber('incident')->name('incidents.lpi');

        Route::get('kontraktor', [ContractorController::class, 'index'])->name('contractors.index');
        Route::post('kontraktor', [ContractorController::class, 'store'])->name('contractors.store');
        Route::put('kontraktor/{contractor}', [ContractorController::class, 'update'])->whereNumber('contractor')->name('contractors.update');
        Route::delete('kontraktor/{contractor}', [ContractorController::class, 'destroy'])->whereNumber('contractor')->name('contractors.destroy');

        Route::get('inspeksi-template', [InspectionTemplateController::class, 'index'])->name('inspection-templates.index');
        Route::post('inspeksi-template', [InspectionTemplateController::class, 'store'])->name('inspection-templates.store');
        Route::put('inspeksi-template/{template}', [InspectionTemplateController::class, 'update'])->whereNumber('template')->name('inspection-templates.update');
        Route::delete('inspeksi-template/{template}', [InspectionTemplateController::class, 'destroy'])->whereNumber('template')->name('inspection-templates.destroy');

        Route::get('jam-kerja', [ManHourController::class, 'index'])->name('manhours.index');
        Route::post('jam-kerja', [ManHourController::class, 'store'])->name('manhours.store');
    });

    Route::middleware('portal.role:admin,hse,manager')->group(function () {
        Route::get('lpi', [LpiController::class, 'index'])->name('lpi.index');
        Route::get('lpi/ekspor', [LpiController::class, 'export'])->name('lpi.export');
    });

    Route::get('inspeksi-dashboard', [InspectionController::class, 'dashboard'])->middleware('portal.role:admin,hse,manager')->name('inspections.dashboard');

    // Laporan & analisis: admin, HSE, manajer.
    Route::middleware('portal.role:admin,hse,manager')->prefix('laporan')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('ringkasan-bulanan', [ReportController::class, 'monthly'])->name('monthly');
        Route::get('ekspor/{dataset}', [ReportController::class, 'export'])->name('export');
    });

    // Administrator: master data, pengaturan, hapus laporan.
    Route::middleware('portal.role:admin')->group(function () {
        Route::delete('insiden/{incident}', [IncidentController::class, 'destroy'])->whereNumber('incident')->name('incidents.destroy');
        Route::delete('inspeksi/{inspection}', [InspectionController::class, 'destroy'])->whereNumber('inspection')->name('inspections.destroy');
        Route::delete('hazard/{hazard}', [HazardController::class, 'destroy'])->whereNumber('hazard')->name('hazards.destroy');

        Route::get('lokasi', [LocationController::class, 'index'])->name('locations.index');
        Route::post('lokasi', [LocationController::class, 'store'])->name('locations.store');
        Route::put('lokasi/{location}', [LocationController::class, 'update'])->whereNumber('location')->name('locations.update');
        Route::delete('lokasi/{location}', [LocationController::class, 'destroy'])->whereNumber('location')->name('locations.destroy');

        Route::get('pengaturan', [SettingController::class, 'show'])->name('settings.index');
        Route::put('pengaturan', [SettingController::class, 'update'])->name('settings.update');
        Route::post('pengaturan/uji-hris', [SettingController::class, 'testHris'])->name('settings.test-hris');
        Route::post('pengaturan/sinkron-hris', [SettingController::class, 'syncHris'])->name('settings.sync-hris');
        Route::post('pengaturan/uji-email', [SettingController::class, 'testMail'])->name('settings.test-mail');
        Route::post('pengaturan/perbarui-email', [SettingController::class, 'refreshMail'])->name('settings.refresh-mail');
        Route::post('pengaturan/email/{emailLog}/kirim-ulang', [SettingController::class, 'resendMail'])->whereNumber('emailLog')->name('settings.resend-mail');
    });
});
