<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\JabatanController;
use App\Http\Controllers\RiwayatJabatanController;
use App\Http\Controllers\RiwayatPangkatController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/
Route::group(attributes: ['middleware' => ['role:super-admin|admin|pimpinan|verifikator|user']], routes: function(): void {
    Route::group(attributes: ['middleware' => ['role:super-admin']], routes: function(): void {
        Route::resource(name: 'permissions', controller: App\Http\Controllers\PermissionController::class);
        Route::get(uri: 'permissions/{permissionId}/delete', action: [App\Http\Controllers\PermissionController::class, 'destroy']);
        Route::post(uri: 'permissions/search', action: [App\Http\Controllers\PermissionController::class, 'search']);
        Route::resource(name: 'roles', controller: App\Http\Controllers\RoleController::class);
        Route::get(uri: 'roles/{roleId}/delete', action: [App\Http\Controllers\RoleController::class, 'destroy']);
        Route::get(uri: 'roles/{roleId}/give-permissions', action: [App\Http\Controllers\RoleController::class, 'addPermissionToRole']);
        Route::put(uri: 'roles/{roleId}/give-permissions', action: [App\Http\Controllers\RoleController::class, 'givePermissionToRole']);
        Route::post(uri: 'roles/search', action: [App\Http\Controllers\RoleController::class, 'search']);
    })->middleware(['auth', 'verified'])->name('roles.');

    Route::group(attributes: ['middleware' => ['role:super-admin|admin']], routes: function(): void {
        Route::resource(name: 'users', controller: App\Http\Controllers\UserController::class);
        Route::get(uri: 'users/{userId}/delete', action: [App\Http\Controllers\UserController::class, 'destroy']);
        Route::post(uri: 'users/search', action: [App\Http\Controllers\UserController::class, 'search']);
    })->middleware(['auth', 'verified'])->name('users.');

    // Add this to your existing routes
    Route::group(['middleware' => ['role:super-admin|admin|pimpinan|verifikator']], function() {
        Route::resource('hari-libur', App\Http\Controllers\HariLiburController::class);
    })->middleware(['auth','verified'])->name('hari-libur.');

    // Inside the middleware group for super-admin|admin|pimpinan|verifikator
    Route::group(attributes: ['middleware' => ['role:super-admin|admin|pimpinan|verifikator']], routes: function(): void {
        Route::get('pegawai/search', [App\Http\Controllers\PegawaiController::class, 'search'])->name('pegawai.search');
        // Add this line for jabatan search
        Route::post('jabatan/search', [App\Http\Controllers\JabatanController::class, 'search'])->name('jabatan.search');

        Route::resource(name: 'pegawai', controller: App\Http\Controllers\PegawaiController::class);
        Route::get(uri: 'pegawai/{pegawaiId}/detail', action: [App\Http\Controllers\PegawaiController::class, 'detail']);
        Route::get(uri: 'pegawai/{pegawaiId}/edit', action: [App\Http\Controllers\PegawaiController::class, 'edit']);
        Route::put(uri: 'pegawai/{pegawaiId}', action: [App\Http\Controllers\PegawaiController::class, 'update']);
        Route::get(uri: 'pegawai/{pegawaiId}/delete', action: [App\Http\Controllers\PegawaiController::class, 'destroy']);
        Route::get(uri: 'pegawai/{pegawaiId}/give-roles', action: [App\Http\Controllers\PegawaiController::class, 'addRoleToUser']);
        Route::put(uri: 'pegawai/{pegawaiId}/give-roles', action: [App\Http\Controllers\PegawaiController::class, 'giveRoleToUser']);
        Route::get(uri: 'pegawai/{pegawaiId}/give-permissions', action: [App\Http\Controllers\PegawaiController::class, 'addPermissionToUser']);
        Route::put(uri: 'pegawai/{pegawaiId}/give-permissions', action: [App\Http\Controllers\PegawaiController::class, 'givePermissionToUser']);
        Route::get(uri: 'pegawai/{pegawaiId}/pangkat', action: [App\Http\Controllers\PegawaiController::class, 'pangkat']);
        Route::get(uri: 'pegawai/{pegawaiId}/jabatan', action: [App\Http\Controllers\PegawaiController::class, 'jabatan']);
        Route::get(uri: 'pegawai/{pegawaiId}/cuti', action: [App\Http\Controllers\PegawaiController::class, 'cuti']);
        Route::get(uri: 'pegawai/{pegawaiId}/cuti/create', action: [App\Http\Controllers\PegawaiController::class, 'createCuti']);
        Route::post(uri: 'pegawai/{pegawaiId}/cuti', action: [App\Http\Controllers\PegawaiController::class,'storeCuti']);
        Route::get(uri: 'pegawai/{pegawaiId}/riwayat_pangkat', action: [App\Http\Controllers\PegawaiController::class, 'riwayatPangkat']);
        Route::get(uri: 'pegawai/{pegawaiId}/riwayat_jabatan', action: [App\Http\Controllers\PegawaiController::class, 'riwayatJabatan']);
        Route::get(uri: 'pegawai/{pegawaiId}/riwayat_cuti', action: [App\Http\Controllers\PegawaiController::class, 'riwayatCuti']);
        Route::get(uri: 'pegawai/{pegawaiId}/riwayat_cuti/{riwayatCutiId}/edit', action: [App\Http\Controllers\PegawaiController::class, 'editRiwayatCuti']);
        Route::put(uri: 'pegawai/{pegawaiId}/riwayat_cuti/{riwayatCutiId}', action: [App\Http\Controllers\PegawaiController::class, 'updateRiwayatCuti']);
        Route::get(uri: 'pegawai/{pegawaiId}/riwayat_cuti/{riwayatCutiId}/delete', action: [App\Http\Controllers\PegawaiController::class, 'destroyRiwayatCuti']);
        Route::resource(name: 'jabatan', controller: App\Http\Controllers\JabatanController::class);
        Route::get(uri: 'riwayat_jabatan/{pegawai_uuid}', action: [App\Http\Controllers\RiwayatJabatanController::class, 'index']);
        Route::get(uri: 'riwayat_jabatan/{pegawai_uuid}/create', action: [App\Http\Controllers\RiwayatJabatanController::class, 'create']);
        Route::post(uri: 'riwayat_jabatan', action: [App\Http\Controllers\RiwayatJabatanController::class, 'store']);
        Route::get(uri: 'riwayat_jabatan/{riwayatJabatanId}/edit', action: [App\Http\Controllers\RiwayatJabatanController::class, 'edit']);
        Route::put(uri: 'riwayat_jabatan/{riwayatJabatanId}', action: [App\Http\Controllers\RiwayatJabatanController::class, 'update']);
        Route::get(uri: 'riwayat_jabatan/{riwayatJabatanId}/delete', action: [App\Http\Controllers\RiwayatJabatanController::class, 'destroy']);

        // Riwayat Pangkat Routes - Order matters!
        Route::prefix('riwayat_pangkat')->name('riwayat_pangkat.')->group(function () {
            Route::get('/create/{uuid}', [RiwayatPangkatController::class, 'create'])->name('create');
            Route::post('/', [RiwayatPangkatController::class, 'store'])->name('store');
            Route::get('/{uuid}', [RiwayatPangkatController::class, 'index'])->name('index');
            Route::get('/{uuid}/edit', [RiwayatPangkatController::class, 'edit'])->name('edit');
            Route::put('/{uuid}', [RiwayatPangkatController::class, 'update'])->name('update');
            Route::delete('/{uuid}', [RiwayatPangkatController::class, 'destroy'])->name('destroy');
        });

        // Riwayat Jabatan Routes - Order matters!
        Route::prefix('riwayat_jabatan')->name('riwayat_jabatan.')->group(function () {
            Route::get('/create/{uuid}', [RiwayatJabatanController::class, 'create'])->name('create');
            Route::post('/', [RiwayatJabatanController::class, 'store'])->name('store');
            Route::get('/{uuid}', [RiwayatJabatanController::class, 'index'])->name('index');
            Route::get('/{uuid}/edit', [RiwayatJabatanController::class, 'edit'])->name('edit');
            Route::put('/{uuid}', [RiwayatJabatanController::class, 'update'])->name('update');
            Route::delete('/{uuid}', [RiwayatJabatanController::class, 'destroy'])->name('destroy');
        });
    })->middleware(['auth', 'verified'])->name('pegawai.');

   // Cuti routes - Allow super-admin access
    Route::group(attributes: ['middleware' => ['role:super-admin|admin|pimpinan|verifikator|user']], routes: function(): void {
        // Inside your cuti routes group, make sure these routes are defined correctly
        Route::prefix('cuti')->name('cuti.')->group(function () {
            // First define routes with specific paths
            Route::get('/update-balance', [App\Http\Controllers\CutiController::class, 'updateBalance'])->name('update-balance');
            Route::get('/update-all-balances', [App\Http\Controllers\CutiController::class, 'updateAllBalances'])->name('update-all-balances');
            Route::get('/balance', [App\Http\Controllers\CutiController::class, 'showBalance'])->name('balance');
            Route::get('/create', [App\Http\Controllers\CutiController::class, 'create'])->name('create');
            Route::get('/', [App\Http\Controllers\CutiController::class, 'index'])->name('index');
            Route::post('/', [App\Http\Controllers\CutiController::class, 'store'])->name('store');

            // Then define routes with parameters
            Route::get('/{uuid}', [App\Http\Controllers\CutiController::class, 'show'])->name('show');
            Route::get('/{uuid}/edit', [App\Http\Controllers\CutiController::class, 'edit'])->name('edit');
            Route::put('/{uuid}', [App\Http\Controllers\CutiController::class, 'update'])->name('update');
            Route::delete('/{uuid}', [App\Http\Controllers\CutiController::class, 'destroy'])->name('destroy');
            Route::get('/{uuid}/verifikasi', [App\Http\Controllers\CutiController::class, 'verifikasi'])->name('verifikasi');
            Route::post('/{uuid}/proses-verifikasi', [App\Http\Controllers\CutiController::class, 'prosesVerifikasi'])->name('proses-verifikasi');
            Route::get('/{uuid}/verifikasi-pimpinan', [App\Http\Controllers\CutiController::class, 'verifikasiPimpinan'])->name('verifikasi-pimpinan');
            Route::post('/{uuid}/proses-verifikasi-pimpinan', [App\Http\Controllers\CutiController::class, 'prosesVerifikasiPimpinan'])->name('proses-verifikasi-pimpinan');
            // Add these routes for atasan pimpinan verification
            Route::get('/{uuid}/verifikasi-atasan-pimpinan', [App\Http\Controllers\CutiController::class, 'verifikasiAtasanPimpinan'])->name('verifikasi-atasan-pimpinan');
            Route::post('/{uuid}/proses-verifikasi-atasan-pimpinan', [App\Http\Controllers\CutiController::class, 'prosesVerifikasiAtasanPimpinan'])->name('proses-verifikasi-atasan-pimpinan');
        });
    });
});

Route::get('/', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Remove the standalone cuti routes since they're now in the middleware group
require __DIR__.'/auth.php';

// Add these routes to your web.php file
Route::resource('izin', App\Http\Controllers\IzinController::class);
Route::get('izin/{uuid}/verifikasi-atasan', [App\Http\Controllers\IzinController::class, 'verifikasiAtasan'])->name('izin.verifikasi-atasan');
Route::post('izin/{uuid}/proses-verifikasi-atasan', [App\Http\Controllers\IzinController::class, 'prosesVerifikasiAtasan'])->name('izin.proses-verifikasi-atasan');
Route::get('izin/{uuid}/verifikasi-pimpinan', [App\Http\Controllers\IzinController::class, 'verifikasiPimpinan'])->name('izin.verifikasi-pimpinan');
Route::post('izin/{uuid}/proses-verifikasi-pimpinan', [App\Http\Controllers\IzinController::class, 'prosesVerifikasiPimpinan'])->name('izin.proses-verifikasi-pimpinan');
