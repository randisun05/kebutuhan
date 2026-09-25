<?php

use App\Http\Controllers\AkunController;
use App\Http\Controllers\AnalitikController;
use App\Http\Controllers\AnjabAbkController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FormasiController;
use App\Http\Controllers\HistoriController;
use App\Http\Controllers\InstansiController;
use App\Http\Controllers\JabatanController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\NotifikasiController;
use App\Http\Controllers\PedomanController;
use App\Http\Controllers\PegawaiController;
use App\Http\Controllers\PenetapanController;
use App\Http\Controllers\PerencanaanController;
use App\Http\Controllers\PeringatanController;
use App\Http\Controllers\SiasnController;
use App\Http\Controllers\SsoSiasnController;
use App\Http\Controllers\UnitKerjaController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UsulanController;
use Illuminate\Support\Facades\Route;

// Login, logout, lupa/reset password, 2FA dan konfirmasi password disediakan Laravel Fortify.

Route::redirect('/', '/dashboard');

// Login SSO SIASN (OpenID Connect)
Route::middleware('guest')->group(function () {
    Route::get('/auth/siasn/redirect', [SsoSiasnController::class, 'redirect'])->name('sso.siasn.redirect');
    Route::get('/auth/siasn/callback', [SsoSiasnController::class, 'callback'])->middleware('throttle:20,1')->name('sso.siasn.callback');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/pedoman', PedomanController::class)->name('pedoman');
    Route::get('/akun', AkunController::class)->name('akun');

    Route::get('/notifikasi', [NotifikasiController::class, 'index'])->name('notifikasi.index');
    Route::post('/notifikasi/baca-semua', [NotifikasiController::class, 'readAll'])->name('notifikasi.read-all');
    Route::get('/notifikasi/{id}', [NotifikasiController::class, 'open'])->name('notifikasi.open');

    // Dashboard data, histori existing, peringatan dini, dan laporan
    Route::get('/analitik', AnalitikController::class)->name('analitik');
    Route::get('/histori', [HistoriController::class, 'index'])->name('histori.index');
    Route::get('/histori/export', [HistoriController::class, 'export'])->name('histori.export');
    Route::get('/peringatan', [PeringatanController::class, 'index'])->name('peringatan.index');
    Route::post('/peringatan/deteksi', [PeringatanController::class, 'deteksi'])->middleware('throttle:5,1')->name('peringatan.deteksi');
    Route::post('/peringatan/{peringatan}/tindak-lanjut', [PeringatanController::class, 'tindakLanjut'])->name('peringatan.tindak-lanjut');
    Route::get('/laporan', [LaporanController::class, 'index'])->name('laporan.index');
    Route::get('/laporan/unduh/{format}', [LaporanController::class, 'unduh'])->name('laporan.unduh');

    // Monitoring kebutuhan vs existing (real-time)
    Route::get('/monitoring', [MonitoringController::class, 'index'])->name('monitoring.index');
    Route::get('/monitoring/export', [MonitoringController::class, 'export'])->name('monitoring.export');
    Route::get('/monitoring/instansi/{instansi}', [MonitoringController::class, 'instansi'])->name('monitoring.instansi');
    Route::get('/monitoring/unit/{unit}', [MonitoringController::class, 'unit'])->name('monitoring.unit');

    // Anjab / ABK (hulu) & perencanaan
    Route::get('/anjab-export', [AnjabAbkController::class, 'export'])->name('anjab.export');
    Route::get('/anjab-template', [AnjabAbkController::class, 'template'])->name('anjab.template');
    Route::post('/anjab-import', [AnjabAbkController::class, 'import'])->name('anjab.import');
    Route::post('/anjab-duplicate', [AnjabAbkController::class, 'duplicateBulk'])->name('anjab.duplicate-bulk');
    Route::get('/anjab-rekap-unit', [AnjabAbkController::class, 'rekapUnit'])->name('anjab.rekap-unit');
    Route::post('/anjab/{anjab}/status', [AnjabAbkController::class, 'toggleStatus'])->name('anjab.status');
    Route::post('/anjab/{anjab}/duplicate', [AnjabAbkController::class, 'duplicate'])->name('anjab.duplicate');
    Route::get('/anjab/{anjab}/pdf', [AnjabAbkController::class, 'pdf'])->name('anjab.pdf');
    Route::resource('anjab', AnjabAbkController::class);

    Route::get('/proyeksi', [PerencanaanController::class, 'proyeksi'])->name('proyeksi');
    Route::get('/proyeksi/export', [PerencanaanController::class, 'exportProyeksi'])->name('proyeksi.export');
    Route::get('/redistribusi', [PerencanaanController::class, 'redistribusi'])->name('redistribusi');
    Route::get('/peta-jabatan', [UnitKerjaController::class, 'petaJabatan'])->name('peta-jabatan');

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
    Route::get('/penetapan/{penetapan}/pdf', [PenetapanController::class, 'pdf'])->name('penetapan.pdf');
    Route::get('/formasi', [FormasiController::class, 'index'])->name('formasi.index');
    Route::put('/formasi', [FormasiController::class, 'update'])->name('formasi.update');

    // Data existing (bezetting), organisasi, dan integrasi SIASN: admin + operator instansi
    Route::middleware('role:admin,operator_instansi')->group(function () {
        Route::get('/pegawai/import', [PegawaiController::class, 'importForm'])->name('pegawai.import.form');
        Route::post('/pegawai/import', [PegawaiController::class, 'import'])->name('pegawai.import');
        Route::get('/pegawai/template', [PegawaiController::class, 'template'])->name('pegawai.template');
        Route::resource('pegawai', PegawaiController::class)->except('show');

        Route::get('/unit-kerja/template', [UnitKerjaController::class, 'template'])->name('unit-kerja.template');
        Route::post('/unit-kerja/import', [UnitKerjaController::class, 'import'])->name('unit-kerja.import');
        Route::post('/unit-kerja/{unitKerja}/toggle', [UnitKerjaController::class, 'toggle'])->name('unit-kerja.toggle');
        Route::resource('unit-kerja', UnitKerjaController::class)->except('show')->parameters(['unit-kerja' => 'unitKerja']);

        Route::get('/siasn', [SiasnController::class, 'index'])->name('siasn.index');
        Route::post('/siasn/sync', [SiasnController::class, 'sync'])->middleware('throttle:10,1')->name('siasn.sync');
        Route::post('/siasn/test', [SiasnController::class, 'test'])->name('siasn.test');
    });

    // Referensi nasional, pengguna, dan audit: admin
    Route::middleware('role:admin')->group(function () {
        Route::resource('instansi', InstansiController::class)->except('show');
        Route::resource('jabatan', JabatanController::class)->except('show');
        Route::resource('users', UserController::class)->except('show');
        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
    });
});
