# SIMPEG Architecture Remediation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the 4 structural gaps from the 2026-08-22 architecture audit: Izin approval race risk, User↔Pegawai nip-join, route file rot, dead dependencies.

**Architecture:** Port the existing, proven CutiApprovalService pattern (DB::transaction + lockForUpdate + expected-status guard) to Izin instead of inventing a shared abstraction. Add a real FK between pegawai and users via backfill migration. Delete dead code/deps. No framework upgrade in this plan.

**Tech Stack:** Laravel 10, PHP 8.1+, PHPUnit (existing tests in `tests/Feature`), MySQL.

## Global Constraints

- PHP >= 8.1, Laravel 10.x — do NOT upgrade the framework in this plan.
- No new composer/npm dependencies.
- Indonesian status strings stay as stored values (display layer unchanged). Izin `status` is an **enum column**: only `['Diajukan','Disetujui Atasan','Ditolak Atasan','Disetujui','Ditolak']` are writable.
- Every task ends green: `php artisan test` must pass before commit.
- Conventional commits matching repo history (`fix(izin): ...`, `refactor(routes): ...`).
- SECURITY-AUDIT.md fixed findings (SEC-001..SEC-018) must not regress — their tests are the guard.

## Reference: current behavior (verified 2026-08-22)

- `app/Services/CutiApprovalService.php` — the pattern to copy: `DB::transaction`, `lockAtStatus($model, $expected)` using `->lockForUpdate()->firstOrFail()`, throw `ValidationException` on mismatch.
- `app/Http/Controllers/IzinController.php:309-361` — `prosesVerifikasiAtasan` / `prosesVerifikasiPimpinan`: inline validate, plain `save()`, NO transaction/lock. The bug class fixed for Cuti in SEC-012.
- `app/Models/Pegawai.php:55` — `belongsTo(User::class, 'nip', 'nip')`; no FK in DB.
- `routes/web.php:8` outer role group lists every role (incl. `user`) = no-op; lines 36–40 duplicate the named block at 51–58.
- Dead deps: `intervention/image` (zero usage), `predis/predis` (drivers: file/database/file), `laravel/sanctum` (one route), `laravel/breeze` (dev scaffold). `illuminate/database: "*"` wildcard.

---

### Task 1: IzinApprovalService (race-safe approval)

**Files:**
- Create: `app/Services/IzinApprovalService.php`
- Modify: `app/Http/Controllers/IzinController.php:309-361`
- Test: `tests/Feature/IzinApprovalRaceTest.php`

**Interfaces:**
- Consumes: `App\Support\IzinType::isSingleLevel(string $jenis): bool` (exists).
- Produces: `IzinApprovalService::applyAtasan(Izin $izin, Pegawai $atasan, string $decision, ?string $catatan): Izin` and `applyPimpinan(Izin $izin, Pegawai $pimpinan, string $decision, ?string $catatan): Izin`. Throws `Illuminate\Validation\ValidationException` when status is not the expected prior state.

**Steps:**
- [x] Write `tests/Feature/IzinApprovalRaceTest.php` modeled on the Cuti duplicate-approval regression test (find it via `grep -rl 'duplicate' tests/Feature` or the SEC-012 test). Cover: (a) atasan approve on status `Diajukan` succeeds and sets `status='Disetujui Atasan'` (two-level) / `'Disetujui'` (single-level); (b) calling `applyAtasan` twice → second throws ValidationException; (c) `applyPimpinan` on `'Diajukan'` (skipped atasan) throws; on `'Disetujui Atasan'` succeeds → `'Disetujui'`; (d) reject paths set `'Ditolak Atasan'` / `'Ditolak'`.
- [x] Run it; confirm it fails (service doesn't exist).
- [x] Create `app/Services/IzinApprovalService.php`: copy `CutiApprovalService` structure. Constants `APPROVE='Disetujui'`, `REJECT='Ditolak'`. `applyAtasan`: transaction + `lockAtStatus($izin, 'Diajukan')`; set `verifikasi_atasan`, `catatan_atasan`, `tanggal_verifikasi_atasan=now()`, approver uuid column if the izin table has one (check migration; if none, skip); status per `IzinType::isSingleLevel`. `applyPimpinan`: lock at `'Disetujui Atasan'`. No balance logic — Izin has no balances.
- [x] Run the new test; green.
- [x] Rewire `IzinController::prosesVerifikasiAtasan`/`prosesVerifikasiPimpinan` to call the service (resolve current approver Pegawai as today). Keep `authorize()` calls.
- [x] `php artisan test` — full suite green.
- [x] Commit: `fix(izin): race-safe approval service (port SEC-012 pattern)`.

### Task 2: Izin FormRequests

**Files:**
- Create: `app/Http/Requests/StoreIzinRequest.php`, `UpdateIzinRequest.php`, `VerifyAtasanIzinRequest.php`, `VerifyPimpinanIzinRequest.php`
- Modify: `app/Http/Controllers/IzinController.php` (replace inline `validateIzinKeluarKantor`/`validateIzinTidakMasukKerja` privates + `$request->validate` calls in store/update/verify methods)
- Test: existing `tests/Feature/IzinPermaValidationTest.php` is the regression guard — do not weaken rules, only move them.

**Interfaces:**
- Produces: typed request objects; controller signatures become `store(StoreIzinRequest $request)` etc.

**Steps:**
- [x] Move rules verbatim from controller privates/inline blocks into the four request classes (`authorize(): true` — policy checks stay in controller).
- [x] Run `php artisan test --filter IzinPermaValidationTest` — green.
- [x] Swap controller method signatures; delete the two private validate methods.
- [x] `php artisan test` — green.
- [x] Commit: `refactor(izin): extract FormRequests from inline validation`.

### Task 3: pegawai.user_uuid FK (replace nip join)

**Files:**
- Create: `database/migrations/2026_08_23_000000_add_user_uuid_to_pegawai_table.php`
- Modify: `app/Models/Pegawai.php` (`user()` → `belongsTo(User::class, 'user_uuid')`), `app/Models/User.php` (add `pegawai(): hasOne`), `app/Http/Controllers/CutiController.php:254,274`, `app/Http/Controllers/IzinController.php:141`
- Test: `tests/Feature/UserPegawaiRelationTest.php` (new)

**Interfaces:**
- Produces: `User::pegawai(): HasOne` (`Auth::user()->pegawai`), FK `pegawai.user_uuid → users.uuid`, unique index.

**Steps:**
- [x] Write failing test: user with linked pegawai → `Auth::user()->pegawai` returns it; nip rename does not break the link (the point of the FK).
- [x] Migration: `$table->foreignUuid('user_uuid')->nullable()->after('nip')`; backfill `DB::update('UPDATE pegawai p JOIN users u ON u.nip = p.nip SET p.user_uuid = u.uuid')`; `->unique()`; FK `references('uuid')->on('users')->nullOnDelete()`. Down: drop FK, index, column.
- [x] Swap both model relations; replace the three controller lookups with `Auth::user()->pegawai` — preserve failure behavior: where code used `->firstOrFail()`, throw the same 404-ish response if relation is null (check each site; IzinController:141 uses `firstOrFail()->uuid`).
- [x] `grep -rn "where('nip'" app/` — no auth-driven lookups remain (report any others found).
- [x] `php artisan migrate && php artisan test` — green.
- [x] Commit: `fix(models): pegawai↔user FK via user_uuid, replace nip join`.

### Task 4: routes/web.php cleanup

**Files:**
- Modify: `routes/web.php:8,36-40,85`

**Steps:**
- [x] `php artisan route:list > /tmp/routes-before.txt`
- [x] Delete lines 36–40 (unnamed `riwayat_jabatan` block shadowed by the named block at 51–58).
- [x] Delete the outer `Route::group(['middleware' => ['role:...|user']], ...)` wrapper (line 8 + its closing brace line 85), unindenting children — every inner group already carries its own role+auth+verified middleware (verified: all five inner groups list `role:` + `auth` + `verified`).
- [x] `php artisan route:list > /tmp/routes-after.txt`; diff — expect only the 5 duplicate rows gone, nothing else.
- [x] `php artisan test` (RouteMiddlewareTest + MiddlewareMatrixTest guard the matrix) — green.
- [x] Commit: `refactor(routes): drop duplicate riwayat_jabatan block and no-op outer role group`.

### Task 5: dependency hygiene

**Files:**
- Modify: `composer.json`, `app/Models/User.php` (remove `HasApiTokens`), `routes/api.php` (delete sanctum route)

**Steps:**
- [x] `grep -rn 'Intervention\|Image::make\|Redis\|redis_\|auth:sanctum\|createToken\|HasApiTokens\|Sanctum' app/ routes/ config/ --include='*.php'` — record hits; expected: only User.php trait + routes/api.php:17.
- [x] composer.json: remove `intervention/image`, `predis/predis`, `laravel/sanctum`, dev `laravel/breeze`, and the `illuminate/database: "*"` line.
- [x] Remove `HasApiTokens` import+trait from User; empty `routes/api.php` route block (keep file, RouteServiceProvider loads it).
- [x] `composer update intervention/image predis/predis laravel/sanctum laravel/breeze --with-all-dependencies` then `composer audit` — clean.
- [x] `php artisan test` + `php artisan config:cache && php artisan route:cache` — both succeed.
- [x] Commit: `chore(deps): remove unused intervention/predis/sanctum/breeze, drop database wildcard`.

---

## Explicitly deferred (separate plans)

- **Laravel 12.61+ upgrade** (RISK-001 CVEs) — own milestone; do AFTER Tasks 1–5 land so the diff stays reviewable.
- Status string enums, shared Cuti/Izin approval engine — only if a third workflow module ever appears. YAGNI now.
