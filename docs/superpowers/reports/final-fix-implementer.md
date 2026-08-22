# Final-Review Minor Findings — Implementation Report

**Branch:** arch-remediation (base 26ccdd4) · **Mode:** follow-up commit per final-review.md verdict
**Tests:** `php artisan test` → **257 passed (502 assertions)** — baseline preserved.

## Findings applied

### 1. Pre-validation authorization gate — APPLIED (with one correction)
- `UpdateIzinRequest::authorize()` now resolves the record and gates via policy, matching `StoreCutiRequest` precedent:
  ```php
  $izin = Izin::where('uuid', $this->route('izin'))->firstOrFail();
  return $this->user()->can('update', $izin);
  ```
  Unauthorized probes now get 403 (missing uuid → 404) instead of a 422 oracle; validation never runs first.
- `StoreIzinRequest::authorize()` → `return $this->user()->can('create', Izin::class);` (IzinPolicy::create exists; `StoreCutiRequest` does the same for Cuti).
- **Correction found while applying:** the final-review fix sketch used `$this->route('uuid')`, but `Route::resource('izin', …)` binds the parameter as `{izin}` (verified via `route:list`); only the custom izin routes use `{uuid}`. `route('uuid')` is `null` inside `UpdateIzinRequest` — a **pre-existing latent bug** this request already carried: `isNoSuratUpdate()` always returned false and the `no_surat_izin` unique rule always `ignore(null, 'uuid')`. The controller's duplicated predicate masked it. All three occurrences now use `route('izin')`; the no-surat update path now actually works as designed (single-field validation). Covered implicitly: PERMA update tests pass; no dedicated no-surat test existed before or after (out of scope).
- Controller `$this->authorize('update'|'create', …)` kept — defense in depth; the extra uuid lookup is trivial, not awkward.
- **Skipped:** `VerifyAtasanIzinRequest` / `VerifyPimpinanIzinRequest` — cuti-side precedent (`VerifyCutiRequest`, `VerifyPimpinanCutiRequest`, `VerifyAtasanPimpinanCutiRequest`) all `return true` with controller-side authorize; their rules leak nothing (`in:Disetujui,Ditolak` + nullable catatan). Per instruction ("apply ONLY where the cuti-side precedent does the same"), left as-is.

### 2. Unused approver params — APPLIED
- `IzinApprovalService::applyAtasan/applyPimpinan` signatures dropped `?Pegawai $atasan`/`?Pegawai $pimpinan` (never read — izin has no who-approved column); `use App\Models\Pegawai;` removed from the service.
- `IzinController::prosesVerifikasiAtasan/Pimpinan` dropped the `Auth::user()->pegawai` resolutions and arguments.
- `tests/Feature/IzinApprovalRaceTest.php` updated (args + now-unused Pegawai factory lines + import removed).

### 3. Duplicated isNoSuratUpdate predicate — APPLIED
- `UpdateIzinRequest::isNoSuratUpdate()` is now `public`; controller `update()` reuses `$request->isNoSuratUpdate()` instead of its inline copy (heuristic `count($this->all()) <= 3` now lives in exactly one place). Cost: one extra uuid-indexed lookup inside the predicate — acceptable.

## Files changed
- `app/Http/Requests/UpdateIzinRequest.php`
- `app/Http/Requests/StoreIzinRequest.php`
- `app/Services/IzinApprovalService.php`
- `app/Http/Controllers/IzinController.php`
- `tests/Feature/IzinApprovalRaceTest.php`

## Notes
- Reports dir remains untracked (final-review Finding 5 decision pending); only code/tests committed.
- No new deps; no framework change; SEC guarantees untouched (policies unchanged — only where they run).
