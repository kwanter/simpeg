<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\JabatanController;
use App\Http\Controllers\RiwayatJabatanController;
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

    Route::group(attributes: ['middleware' => ['role:super-admin|admin|pimpinan|verifikator']], routes: function(): void {
        Route::resource(name: 'pegawai', controller: App\Http\Controllers\PegawaiController::class);
        Route::get(uri: 'pegawai/{pegawaiId}/detail', action: [App\Http\Controllers\PegawaiController::class, 'detail']);
        Route::resource(name: 'jabatan', controller: App\Http\Controllers\JabatanController::class);
        Route::get(uri: 'riwayat_jabatan/{pegawai_uuid}', action: [App\Http\Controllers\RiwayatJabatanController::class, 'index']);
        Route::get(uri: 'riwayat_jabatan/{pegawai_uuid}/create', action: [App\Http\Controllers\RiwayatJabatanController::class, 'create']);
        Route::post(uri: 'riwayat_jabatan', action: [App\Http\Controllers\RiwayatJabatanController::class, 'store']);
        Route::get(uri: 'riwayat_jabatan/{riwayatJabatanId}/edit', action: [App\Http\Controllers\RiwayatJabatanController::class, 'edit']);
        Route::put(uri: 'riwayat_jabatan/{riwayatJabatanId}', action: [App\Http\Controllers\RiwayatJabatanController::class, 'update']);
        Route::get(uri: 'riwayat_jabatan/{riwayatJabatanId}/delete', action: [App\Http\Controllers\RiwayatJabatanController::class, 'destroy']);

    })->middleware(['auth', 'verified'])->name('pegawai.');

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

Route::resource('jabatan', JabatanController::class);
Route::resource('riwayat_jabatan', RiwayatJabatanController::class);

require __DIR__.'/auth.php';
