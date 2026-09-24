<?php

use App\Http\Controllers\AnjabAbkController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InstansiController;
use App\Http\Controllers\JabatanController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\PedomanController;
use App\Http\Controllers\PegawaiController;
use App\Http\Controllers\PenetapanController;
use App\Http\Controllers\UnitKerjaController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UsulanController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:20,1')->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/pedoman', PedomanController::class)->name('pedoman');

    // Monitoring kebutuhan vs existing (real-time)
    Route::get('/monitoring', [MonitoringController::class, 'index'])->name('monitoring.index');
    Route::get('/monitoring/export', [MonitoringController::class, 'export'])->name('monitoring.export');
    Route::get('/monitoring/instansi/{instansi}', [MonitoringController::class, 'instansi'])->name('monitoring.instansi');
    Route::get('/monitoring/unit/{unit}', [MonitoringController::class, 'unit'])->name('monitoring.unit');

    // Anjab / ABK (hulu)
    Route::post('/anjab/{anjab}/status', [AnjabAbkController::class, 'toggleStatus'])->name('anjab.status');
    Route::resource('anjab', AnjabAbkController::class);

    // Usulan kebutuhan -> verifikasi BKN -> validasi KemenPANRB -> penetapan (hilir)
    Route::post('/usulan/{usulan}/tarik-abk', [UsulanController::class, 'tarikAbk'])->name('usulan.tarik-abk');
    Route::post('/usulan/{usulan}/details', [UsulanController::class, 'storeDetail'])->name('usulan.details.store');
    Route::put('/usulan/{usulan}/details', [UsulanController::class, 'updateDetails'])->name('usulan.details.update');
    Route::delete('/usulan/{usulan}/details/{detail}', [UsulanController::class, 'destroyDetail'])->name('usulan.details.destroy');
    Route::post('/usulan/{usulan}/aksi', [UsulanController::class, 'action'])->middleware('throttle:30,1')->name('usulan.action');
    Route::get('/usulan/{usulan}/berkas/{jenis}', [UsulanController::class, 'berkas'])->name('usulan.berkas');
    Route::post('/usulan/{usulan}', [UsulanController::class, 'update'])->name('usulan.update.multipart');
    Route::resource('usulan', UsulanController::class);

    Route::get('/penetapan', [PenetapanController::class, 'index'])->name('penetapan.index');
    Route::get('/penetapan/{penetapan}', [PenetapanController::class, 'show'])->name('penetapan.show');

    // Data existing (bezetting) & struktur organisasi: admin + operator instansi
    Route::middleware('role:admin,operator_instansi')->group(function () {
        Route::get('/pegawai/import', [PegawaiController::class, 'importForm'])->name('pegawai.import.form');
        Route::post('/pegawai/import', [PegawaiController::class, 'import'])->name('pegawai.import');
        Route::get('/pegawai/template', [PegawaiController::class, 'template'])->name('pegawai.template');
        Route::resource('pegawai', PegawaiController::class)->except('show');
        Route::resource('unit-kerja', UnitKerjaController::class)->except('show')->parameters(['unit-kerja' => 'unitKerja']);
    });

    // Referensi nasional & pengguna: admin
    Route::middleware('role:admin')->group(function () {
        Route::resource('instansi', InstansiController::class)->except('show');
        Route::resource('jabatan', JabatanController::class)->except('show');
        Route::resource('users', UserController::class)->except('show');
    });
});
