<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RiwayatJabatanController;
use App\Http\Controllers\RiwayatPangkatController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['role:super-admin|admin|atasan-pimpinan|pimpinan|verifikator|user']], function (): void {
    Route::group(['middleware' => ['role:super-admin', 'auth', 'verified']], function (): void {
        Route::resource('permissions', App\Http\Controllers\PermissionController::class);
        Route::post('permissions/search', [App\Http\Controllers\PermissionController::class, 'search']);
        Route::resource('roles', App\Http\Controllers\RoleController::class);
        Route::get('roles/{roleId}/give-permissions', [App\Http\Controllers\RoleController::class, 'addPermissionToRole']);
        Route::put('roles/{roleId}/give-permissions', [App\Http\Controllers\RoleController::class, 'givePermissionToRole']);
        Route::post('roles/search', [App\Http\Controllers\RoleController::class, 'search']);
    })->name('roles.');

    Route::group(['middleware' => ['role:super-admin|admin', 'auth', 'verified']], function (): void {
        Route::resource('users', App\Http\Controllers\UserController::class);
        Route::post('users/search', [App\Http\Controllers\UserController::class, 'search']);
    })->name('users.');

    Route::group(['middleware' => ['role:super-admin|admin|atasan-pimpinan|pimpinan|verifikator', 'auth', 'verified']], function (): void {
        Route::resource('hari-libur', App\Http\Controllers\HariLiburController::class);
    })->name('hari-libur.');

    Route::group(['middleware' => ['role:super-admin|admin|atasan-pimpinan|pimpinan|verifikator', 'auth', 'verified']], function (): void {
        Route::get('pegawai/search', [App\Http\Controllers\PegawaiController::class, 'search'])->name('pegawai.search');
        Route::post('jabatan/search', [App\Http\Controllers\JabatanController::class, 'search'])->name('jabatan.search');

        Route::resource('pegawai', App\Http\Controllers\PegawaiController::class)->except('show');
        Route::get('pegawai/{pegawaiId}/detail', [App\Http\Controllers\PegawaiController::class, 'detail'])->name('pegawai.detail');
        // Dead give-roles / nested cuti / riwayat_* on PegawaiController removed (P0-003) — use Role/User + dedicated riwayat controllers.
        Route::resource('jabatan', App\Http\Controllers\JabatanController::class);

        Route::get('riwayat_jabatan/{pegawai_uuid}', [RiwayatJabatanController::class, 'index']);
        Route::get('riwayat_jabatan/{pegawai_uuid}/create', [RiwayatJabatanController::class, 'create']);
        Route::post('riwayat_jabatan', [RiwayatJabatanController::class, 'store']);
        Route::get('riwayat_jabatan/{riwayatJabatanId}/edit', [RiwayatJabatanController::class, 'edit']);
        Route::put('riwayat_jabatan/{riwayatJabatanId}', [RiwayatJabatanController::class, 'update']);

        Route::prefix('riwayat_pangkat')->name('riwayat_pangkat.')->group(function () {
            Route::get('/create/{uuid}', [RiwayatPangkatController::class, 'create'])->name('create');
            Route::post('/', [RiwayatPangkatController::class, 'store'])->name('store');
            Route::get('/{uuid}', [RiwayatPangkatController::class, 'index'])->name('index');
            Route::get('/{uuid}/edit', [RiwayatPangkatController::class, 'edit'])->name('edit');
            Route::put('/{uuid}', [RiwayatPangkatController::class, 'update'])->name('update');
            Route::delete('/{uuid}', [RiwayatPangkatController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('riwayat_jabatan')->name('riwayat_jabatan.')->group(function () {
            Route::get('/create/{uuid}', [RiwayatJabatanController::class, 'create'])->name('create');
            Route::post('/', [RiwayatJabatanController::class, 'store'])->name('store');
            Route::get('/{uuid}', [RiwayatJabatanController::class, 'index'])->name('index');
            Route::get('/{uuid}/edit', [RiwayatJabatanController::class, 'edit'])->name('edit');
            Route::put('/{uuid}', [RiwayatJabatanController::class, 'update'])->name('update');
            Route::delete('/{uuid}', [RiwayatJabatanController::class, 'destroy'])->name('destroy');
        });
    })->name('pegawai.');

    // Cuti routes
    Route::group(['middleware' => ['role:super-admin|admin|atasan-pimpinan|pimpinan|verifikator|user', 'auth', 'verified']], function (): void {
        Route::prefix('cuti')->name('cuti.')->group(function () {
            Route::get('/update-balance', [App\Http\Controllers\CutiController::class, 'updateBalance'])->name('update-balance');
            Route::get('/update-all-balances', [App\Http\Controllers\CutiController::class, 'updateAllBalances'])->name('update-all-balances');
            Route::get('/balance', [App\Http\Controllers\CutiController::class, 'showBalance'])->name('balance');
            Route::get('/create', [App\Http\Controllers\CutiController::class, 'create'])->name('create');
            Route::get('/', [App\Http\Controllers\CutiController::class, 'index'])->name('index');
            Route::post('/', [App\Http\Controllers\CutiController::class, 'store'])->name('store');

            Route::get('/{uuid}', [App\Http\Controllers\CutiController::class, 'show'])->name('show');
            Route::get('/{uuid}/edit', [App\Http\Controllers\CutiController::class, 'edit'])->name('edit');
            Route::put('/{uuid}', [App\Http\Controllers\CutiController::class, 'update'])->name('update');
            Route::delete('/{uuid}', [App\Http\Controllers\CutiController::class, 'destroy'])->name('destroy');
            Route::get('/{uuid}/verifikasi', [App\Http\Controllers\CutiController::class, 'verifikasi'])->name('verifikasi');
            Route::post('/{uuid}/proses-verifikasi', [App\Http\Controllers\CutiController::class, 'prosesVerifikasi'])->name('proses-verifikasi');
            Route::get('/{uuid}/verifikasi-pimpinan', [App\Http\Controllers\CutiController::class, 'verifikasiPimpinan'])->name('verifikasi-pimpinan');
            Route::post('/{uuid}/proses-verifikasi-pimpinan', [App\Http\Controllers\CutiController::class, 'prosesVerifikasiPimpinan'])->name('proses-verifikasi-pimpinan');
            Route::get('/{uuid}/verifikasi-atasan-pimpinan', [App\Http\Controllers\CutiController::class, 'verifikasiAtasanPimpinan'])->name('verifikasi-atasan-pimpinan');
            Route::post('/{uuid}/proses-verifikasi-atasan-pimpinan', [App\Http\Controllers\CutiController::class, 'prosesVerifikasiAtasanPimpinan'])->name('proses-verifikasi-atasan-pimpinan');
            Route::get('/{uuid}/pdf', [App\Http\Controllers\CutiController::class, 'generatePdf'])->name('pdf');
            Route::get('/{uuid}/dokumen', [App\Http\Controllers\CutiController::class, 'downloadDocument'])->name('dokumen');
        });
    });
});

Route::get('/', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

// Izin routes — fixed-path PERMA routes BEFORE resource to avoid {uuid} collision
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('izin/keluar-kantor', [App\Http\Controllers\IzinController::class, 'indexKeluarKantor'])->name('izin.index-keluar-kantor');
    Route::get('izin/keluar-kantor/create', [App\Http\Controllers\IzinController::class, 'createKeluarKantor'])->name('izin.create-keluar-kantor');
    Route::get('izin/tidak-masuk', [App\Http\Controllers\IzinController::class, 'indexTidakMasuk'])->name('izin.index-tidak-masuk');
    Route::get('izin/tidak-masuk/create', [App\Http\Controllers\IzinController::class, 'createTidakMasuk'])->name('izin.create-tidak-masuk');
    Route::resource('izin', App\Http\Controllers\IzinController::class);
    Route::get('izin/{uuid}/verifikasi-atasan', [App\Http\Controllers\IzinController::class, 'verifikasiAtasan'])->name('izin.verifikasi-atasan');
    Route::post('izin/{uuid}/proses-verifikasi-atasan', [App\Http\Controllers\IzinController::class, 'prosesVerifikasiAtasan'])->name('izin.proses-verifikasi-atasan');
    Route::get('izin/{uuid}/verifikasi-pimpinan', [App\Http\Controllers\IzinController::class, 'verifikasiPimpinan'])->name('izin.verifikasi-pimpinan');
    Route::post('izin/{uuid}/proses-verifikasi-pimpinan', [App\Http\Controllers\IzinController::class, 'prosesVerifikasiPimpinan'])->name('izin.proses-verifikasi-pimpinan');
    Route::get('izin/{uuid}/pdf', [App\Http\Controllers\IzinController::class, 'generatePdf'])->name('izin.pdf');
    Route::get('izin/{uuid}/dokumen', [App\Http\Controllers\IzinController::class, 'downloadDocument'])->name('izin.dokumen');
});
