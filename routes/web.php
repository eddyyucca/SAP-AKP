<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\HazardController;
use App\Http\Controllers\InspectionController;
use App\Http\Controllers\InspectionTemplateController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StandardController;
use App\Http\Controllers\TargetController;
use Illuminate\Support\Facades\Route;

// Semua halaman sudah wajib login Portal (config/sso.php, protect_all). Level dicek per grup di bawah
// (dan sekali lagi di controller lewat App\Support\Access).
Route::redirect('/', '/ssap')->name('home');

Route::middleware(['portal.role:admin,hse,manager,user', 'throttle:120,1'])->prefix('ssap')->name('ssap.')->group(function () {
    Route::get('/', [DashboardController::class, 'home'])->name('home');
    Route::get('standar', StandardController::class)->name('standard');
    Route::get('karyawan', [EmployeeController::class, 'search'])->name('employees.search');
    Route::get('target', [TargetController::class, 'index'])->name('targets');

    // Hazard report: semua pengguna melapor; melihat dibatasi per laporan (Hazard::visibleTo).
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

    // Pelapor (= PIC) mencatat perkembangan dan tindakan perbaikan hazardnya; dicek per laporan di controller.
    Route::post('hazard/{hazard}/status', [HazardController::class, 'resolve'])->whereNumber('hazard')->name('hazards.resolve');

    Route::get('lampiran/{attachment}', [AttachmentController::class, 'show'])->whereNumber('attachment')->name('attachments.show');
    Route::delete('lampiran/{attachment}', [AttachmentController::class, 'destroy'])->whereNumber('attachment')->name('attachments.destroy');

    // Petugas HSE & Administrator: menangani laporan.
    Route::middleware('portal.role:admin,hse')->group(function () {

        Route::get('inspeksi-template', [InspectionTemplateController::class, 'index'])->name('inspection-templates.index');
        Route::post('inspeksi-template', [InspectionTemplateController::class, 'store'])->name('inspection-templates.store');
        Route::put('inspeksi-template/{template}', [InspectionTemplateController::class, 'update'])->whereNumber('template')->name('inspection-templates.update');
        Route::delete('inspeksi-template/{template}', [InspectionTemplateController::class, 'destroy'])->whereNumber('template')->name('inspection-templates.destroy');
    });

    // Dashboard rinci, laporan & analisis: admin, HSE, manajer.
    Route::middleware('portal.role:admin,hse,manager')->group(function () {
        Route::get('hazard-dashboard', [DashboardController::class, 'hazards'])->name('hazards.dashboard');
        Route::get('inspeksi-dashboard', [InspectionController::class, 'dashboard'])->name('inspections.dashboard');
        Route::get('akuntabilitas', [DashboardController::class, 'accountability'])->name('accountability');

        Route::prefix('laporan')->name('reports.')->group(function () {
            Route::get('/', [ReportController::class, 'index'])->name('index');
            Route::get('ringkasan-bulanan', [ReportController::class, 'monthly'])->name('monthly');
            Route::get('ekspor/{dataset}', [ReportController::class, 'export'])->name('export');
        });
    });

    // Administrator: master data, pengaturan, hapus laporan.
    Route::middleware('portal.role:admin')->group(function () {
        Route::delete('hazard/{hazard}', [HazardController::class, 'destroy'])->whereNumber('hazard')->name('hazards.destroy');
        Route::delete('inspeksi/{inspection}', [InspectionController::class, 'destroy'])->whereNumber('inspection')->name('inspections.destroy');

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
