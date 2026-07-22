# Project Discovery Draft
> Date: 2026-06-08 | Project: Simpeg
> Source: read-only codebase exploration + `simpeg-codebase` skill + current verification output

## Summary

Simpeg is a Laravel 10 personnel-management app with Blade UI, Spatie Permission RBAC, UUID primary keys, Cuti and Izin approval workflows, PDF generation, file uploads, WorkdayService business-day calculation, tests, CI, and 3 Docker modes.

Overall maturity: medium. Core Cuti/Izin policy and PERMA No. 7/2016 behavior is now meaningfully tested, but controller-layer complexity remains the main risk. The highest-value Phase 1 work is not feature work; it is safe extraction of duplicated business logic into small services/FormRequests plus a controller/policy/route test safety net.

Current verification run during this draft:

```text
Focused tests: 96 passed (153 assertions)
Pint: 135 files PASS
composer audit: FAIL — 11 advisories affecting 7 packages + 1 abandoned package
```

Important: previous context said 0 CVEs. Current `composer audit` on 2026-06-08 reports new 2026 advisories for Laravel/Symfony packages. Treat security dependency update as Track A.

## Methodology

- Loaded project-specific `simpeg-codebase` skill and Phase 0 `brainstorming-explorer` workflow.
- Read/analyzed `routes/web.php`, route list, controllers, policies, models, services, middleware, Blade view inventory, seeders, factories, tests, Composer/NPM manifests, Docker compose files, CI workflows, and recent git history.
- Ranked findings by severity using current code evidence and verification output.
- This draft is read-only except writing this `discovery-draft.md` artifact.

Evidence highlights:

```text
routes/web.php:18-145                 Main route surface; role/auth/verified groups
app/Http/Controllers/CutiController.php:17-788   789-line Cuti workflow controller
app/Http/Controllers/IzinController.php:17-558   559-line Izin workflow controller
app/Policies/CutiPolicy.php:34-152    Cuti policy methods
app/Policies/IzinPolicy.php:34-133    Izin policy methods
app/Services/WorkdayService.php:9-29  Business-day calculation
resources/views/cuti/*.blade.php      Largest view cluster; 1.6k+ lines per skill snapshot
resources/views/izin/*.blade.php      PERMA-specific forms/index/PDF templates
database/seeders/UserRolePermissionSeeder.php:34-187  RBAC seed logic
.github/workflows/*.yml               PHP + Playwright CI
composer.json / package.json          Dependency surfaces
docker-compose.yml / docker-compose.dev.yml / docker/ Production assets
```

## Findings (ranked by severity)

### P0 — Security dependency drift: `composer audit` no longer clean

Current `composer audit --no-interaction` reports 11 advisories affecting 7 packages, including Laravel framework CRLF injection and multiple Symfony mailer/mime/routing/http-foundation/yaml advisories. It also reports abandoned `php-http/message-factory`.

Evidence:

```text
composer audit output: 11 advisories, 7 packages
laravel/framework CVE-2026-48019
symfony/http-foundation CVE-2026-48736
symfony/mailer CVE-2026-45068
symfony/mime CVE-2026-45067/CVE-2026-45070
symfony/routing CVE-2026-45065/CVE-2026-48784
symfony/yaml CVE-2026-45133/CVE-2026-45304/CVE-2026-45305
symfony/polyfill-intl-idn CVE-2026-46644
composer.json:8-21 pins Laravel ^10.10 and Symfony transitives through Laravel
```

Impact: production risk. Some advisories involve email/header/URL behavior, relevant to auth/email verification and generated URLs.

Recommended extraction/fix: dependency update track first, then full suite. Likely `composer update laravel/framework symfony/* --with-all-dependencies` constrained to Laravel 10-supported patched versions.

### P1 — Controller bloat: CutiController is oversized and mixes business, persistence, auth, upload, PDF, balance logic

`CutiController` is 789 lines with 18 methods. It contains validation, file upload/delete, annual leave balance rules, Cuti Besar eligibility, mutual exclusion checks, workday counting, verification transitions, PDF guard logic, and balance recalculation.

Evidence:

```text
app/Http/Controllers/CutiController.php:17-788   18 methods, 789 lines
store business rules: 131-245
update business rules duplicated: 309-453
file upload/delete: 235-240, 438-448, 466-468
verification transitions: 576-709
balance actions: 713-761
PDF gate/download: 765-787
ensureCutiBalance references: 95,157,262,356,496,532,568,650,703,723
```

Extract candidates:

1. `CutiValidationService` or `CutiEligibilityService`
   - Annual leave remaining-days check: `CutiController.php:154-178`, `353-381`
   - Cuti Besar 5-year + max 90 workdays + mutual exclusion: `179-227`, `383-432`
2. `CutiBalanceService`
   - `ensureCutiBalance()` controller-private method: `95-127`
   - balance increment/decrement on verification/rejection: `prosesVerifikasi*` regions, esp `650-706`
   - wrapper around `CutiBalance::checkAndUpdateBalance()`/`bulkCheckAndUpdateBalance()`
3. `CutiDocumentService`
   - store/delete `public/dokumen/cuti`: `235-240`, `438-448`, `466-468`
4. `CutiApprovalService`
   - status machine: Pending → Disetujui Verifikator → Disetujui Pimpinan → Disetujui Atasan Pimpinan, plus rejected variants.
5. `CutiPdfService`
   - filename + PDF generation, now split between controller and model: `CutiController.php:782-787`, `app/Models/Cuti.php:103-109`
6. FormRequests:
   - `StoreCutiRequest`, `UpdateCutiRequest`, `VerifyCutiRequest`, `UpdateCutiNoSuratRequest`.

### P1 — IzinController is now 559 lines after PERMA expansion; PERMA rules are controller-private, not domain-level

`IzinController` grew to 559 lines, with 17 methods. PERMA-specific validation is implemented as private controller methods, plus duplicated dropdown/role query setup and duplicated jenis lists.

Evidence:

```text
app/Http/Controllers/IzinController.php:17-558  17 methods, 559 lines
PERMA validation private methods: 22-60
index role scoping: 62-104
jenisIzin duplicated arrays: 117-128 and 159-170
store validation + PERMA branching: 187-250
PDF template selection/download: 423-440
PERMA create/index methods: 446-558
```

Extract candidates:

1. `IzinTypeRules` config/model constant
   - Canonical jenis_izin list; remove duplicate arrays at `117-128` and `159-170`.
2. `IzinValidationService` or `IzinRequestRules`
   - Same-day/time-range rules for `Izin Keluar Kantor`/`Izin Pulang Cepat`: `26-41`, `217-228`
   - Max 2 workdays for `Izin Tidak Masuk Kerja`: `48-60`, `228-238`
3. `IzinApprovalService`
   - Single-level vs two-level status transition rules currently split between controller and `IzinPolicy::verifyPimpinan()`.
4. `IzinQueryScopeService`
   - Role-scoped index query repeated in `index()`, `indexKeluarKantor()`, `indexTidakMasuk()`.
5. `ApproverDirectoryService`
   - repeated `User::role(...)->join('pegawai'...)` at `131-139`, `173-181`, `458-466`, `484-492`.
6. FormRequests:
   - `StoreIzinRequest`, `UpdateIzinRequest`, `VerifyAtasanIzinRequest`, `VerifyPimpinanIzinRequest`.

### P1 — Policy coverage is strong for Cuti/Izin but weak/missing for most other domains

Only `CutiPolicy.php` and `IzinPolicy.php` exist. Cuti/Izin controller actions mostly call `$this->authorize()`: Cuti has 15 authorize lines, Izin has 16. Other controllers rely on route/constructor middleware only.

Evidence:

```text
app/Policies/: CutiPolicy.php, IzinPolicy.php only
CutiController authorize lines: 55,133,251,274,276,318,333,459,480,507,543,586,610,666,768
IzinController authorize lines: 64,109,148,189,256,264,330,347,357,367,400,426,448,476,502,531
HariLiburController: 0 authorize calls; constructor role middleware only: 11-15
PegawaiController: 0 authorize calls; permission middleware only: 14-23
UserController/RoleController/PermissionController/Riwayat*Controller/JabatanController: 0 authorize calls by scan
```

Weak points:

- `HariLiburController` create/store/update/delete are admin-only via middleware, but no `HariLiburPolicy`; workday correctness depends on this data.
- `PegawaiController` sensitive PII CRUD uses permission middleware, no per-record policy. `detail()` exposes linked user email after permission check only by route middleware: `PegawaiController.php:148-159`.
- `UserController`, `RoleController`, `PermissionController` manage privileged data but use only role/permission middleware.

Recommended: keep middleware, add policies for `Pegawai`, `HariLibur`, `User`, `Role`, `Permission` if future record-scoped behavior is expected. At minimum add controller/action tests for middleware enforcement.

### P1 — Route map has legacy/generated route names and overlapping manual routes

Route cache currently works, but route quality is inconsistent. Resource routes are mixed with explicit same-path/same-action routes, producing many `generated::...` route names and duplicate-ish URL surfaces.

Evidence:

```text
routes/web.php:18-145
Route::resource('pegawai', ...): 44
manual pegawai edit/update routes duplicate resource semantics: 45-47
manual permission/role/user search routes have no names in declarations: 21,25,30
manual riwayat_jabatan old routes before prefix group: 64-68
prefix riwayat_jabatan named group repeats similar surfaces: 80-88
route:list shows generated:: names for pegawai detail/edit/update and riwayat routes
```

Specific route/middleware concerns:

- `Route::group(...)->name('roles.')` wraps permissions routes too at `18-26`; resource names still show `permissions.*`, but group naming is confusing.
- Route ordering matters for `cuti/update-balance`, `cuti/update-all-balances`, `izin/keluar-kantor`, `izin/tidak-masuk` before `/{uuid}`; current order is okay but fragile.
- Some manual routes lack explicit names, making tests/navigation harder and causing `generated::` route names.
- `api/user` exists in route list outside main app concerns; check whether Sanctum API route is intentional for this server-rendered app.

Recommended: route cleanup track after tests; do not change before adding route-list assertions and `php artisan route:cache` gate.

### P2 — View duplication and complexity concentrated in Cuti + PERMA Izin

Largest views are Cuti show/create/verification forms and navigation. Cuti verification pages likely share large repeated field layouts. Izin PERMA views duplicate create/index patterns.

Evidence:

```text
resources/views/cuti/show.blade.php                       289 lines
resources/views/cuti/create.blade.php                     275 lines
resources/views/cuti/verifikasi-atasan-pimpinan.blade.php 233 lines
resources/views/cuti/verifikasi.blade.php                 212 lines
resources/views/cuti/verifikasi-pimpinan.blade.php        202 lines
resources/views/layouts/navigation.blade.php              197 lines
resources/views/izin/show.blade.php                       167 lines
resources/views/izin/create.blade.php                     129 lines
resources/views/izin/create-tidak-masuk.blade.php         110 lines
resources/views/izin/create-keluar-kantor.blade.php       108 lines
resources/views/izin/index.blade.php                       95 lines
resources/views/izin/index-keluar-kantor.blade.php         likely similar index variant
resources/views/izin/index-tidak-masuk.blade.php           likely similar index variant
```

Extract candidates:

- `resources/views/cuti/partials/detail-card.blade.php`
- `resources/views/cuti/partials/balance-card.blade.php`
- `resources/views/cuti/partials/approval-timeline.blade.php`
- `resources/views/cuti/partials/verification-form.blade.php`
- `resources/views/izin/partials/form-fields.blade.php`
- `resources/views/izin/partials/index-table.blade.php`
- `resources/views/layouts/partials/nav-izin.blade.php`

### P2 — Test coverage is strong for policies/PERMA validation, weak for controller integration and non-Cuti/Izin domains

Current test inventory is good around policy/service/PERMA. Missing areas: full Cuti workflow feature tests, Izin multi-step feature tests beyond validation, Pegawai/User/Role/Permission/HariLibur controller tests, route/middleware tests, Docker/CI smoke, upload validation tests, PDF authorization tests.

Evidence:

```text
tests/Unit/Policies/CutiPolicyTest.php      37 tests
tests/Unit/Policies/IzinPolicyTest.php      39 tests
tests/Feature/IzinPermaValidationTest.php   7 tests
tests/Unit/Services/WorkdayServiceTest.php  6 tests
tests/Unit/Middleware/SecureHeadersMiddlewareTest.php 7 tests
Breeze auth/profile tests exist
No discovered feature tests named CutiWorkflow, IzinApprovalWorkflow, PegawaiController, HariLiburController, RolePermissionController, Upload, Pdf
Focused run: 96 passed
```

High-value tests to add:

1. Cuti annual balance approve/reject rollback feature tests.
2. Cuti Besar vs Cuti Tahunan mutual exclusion feature tests.
3. Cuti assigned pimpinan/atasan cannot approve unassigned record feature tests.
4. Izin Keluar Kantor/Pulang Cepat atasan approval directly sets final status and PDF path.
5. Izin Tidak Masuk atasan → pimpinan two-level approval and max 2 workdays.
6. Upload validation: disallow SVG/HTML/polyglot, enforce MIME + size + storage deletion.
7. Route/middleware matrix for all roles on Pegawai/HariLibur/Role/User/Permission.
8. PDF authorization and no_surat prerequisites.
9. Docker smoke in CI: build Apache dev or production image.

### P2 — Docker deployment maturity: useful modes exist, but production mode lacks compose/healthcheck clarity

There are 3 modes: Sail (`docker-compose.yml`), Apache dev (`docker-compose.dev.yml`), and production Dockerfiles/configs per skill. Current root has `compose.dev.yaml` and `compose.prod.yaml` too, plus `docker-compose.dev.yml` and `docker-compose.yml`, creating naming ambiguity.

Evidence:

```text
docker-compose.yml: Sail PHP 8.4 + MySQL 8, vendor Sail runtime path
docker-compose.dev.yml: Apache PHP 8.2 + MySQL 8 + Redis, bind mount, host mysql data
docker/production/* exists per repo map/skill
root also contains compose.dev.yaml and compose.prod.yaml
CI workflows do not build Docker images
```

Issues:

- Sail mode depends on `./vendor/laravel/sail/runtimes/8.4`; fresh clone without composer install cannot build Sail immediately.
- Apache dev mode bind-mounts `./docker/mysql-data:/var/lib/mysql`; risk of local permission/state drift, and it is not ignored/managed visibly in the snippet.
- Production maturity unclear unless `compose.prod.yaml` is maintained; CI does not build production image or run smoke tests.
- PHP versions diverge: composer `php ^8.1`, Apache dev PHP 8.2, Sail/prod PHP 8.4 per skill. Acceptable, but should be documented/tested.

Recommended: standardize file naming; add Docker build smoke job; document one canonical dev mode and one canonical prod deploy path.

### P2 — PERMA No. 7/2016 compliance is partly implemented, but source of truth is scattered

Implemented rules for Izin Keluar Kantor, Izin Pulang Cepat, and Izin Tidak Masuk Kerja are present and tested, but the canonical type/rule mapping is scattered across migrations, controller arrays, controller private methods, policy special cases, dedicated Blade views, and PDF match logic.

Evidence:

```text
IzinController PERMA comments/rules: 22-60
same-day/time validation: 26-41, 217-228
max 2 workdays: 48-60, 228-238
single-level policy denial: IzinPolicy.php:97-101
jenis list duplicated: IzinController.php:117-128,159-170
PDF selection in IzinController: 423-440
PERMA tests: tests/Feature/IzinPermaValidationTest.php, 7 tests pass
```

Gaps/risks:

- No `IzinType` enum/config object; future type additions can drift.
- Same-day validation uses `date_equals:today`/`now()->toDateString()`, coupling forms to current date; confirm regulatory/product expectation for pre-submission vs only day-of request.
- Workday semantics depend on `HariLibur`; current tests pass, but `WorkdayService.php:15-23` counts anything not in holiday table. If weekends are excluded only because seeded/test holidays include weekends, document that.
- PDF layout compliance should be visually reviewed against Lampiran II/III; tests validate behavior, not PDF form fidelity.

### P2 — Security headers present but CSP intentionally weak; HSTS may be unsafe in HTTP dev

Secure headers middleware is implemented and tested. CSP allows unsafe inline/eval due Blade/CDN/Alpine/Bootstrap setup. HSTS is always set.

Evidence:

```text
app/Http/Middleware/SecureHeadersMiddleware.php:19-34
script-src includes 'unsafe-inline' 'unsafe-eval': 27
style-src includes 'unsafe-inline': 28
HSTS always set: 24
SecureHeadersMiddleware tests: 7 passed
```

Concerns:

- CSP is better than absent but not strict. Inline/eval weakens XSS mitigation.
- HSTS should generally only be sent over HTTPS/prod; sending on local HTTP is not harmful to browser policy unless hostname reused, but should be environment-gated for correctness.
- CDN scripts/styles in layout increase SRI/CSP management burden.

Recommended: add environment-aware HSTS; add nonce/SRI plan if hardening UI.

### P2 — File upload validation mostly good, but should be centralized and tested

Cuti/Izin document upload validates file extension + MIME + max 2MB. Pegawai photo upload validates image, mimes, max size, dimensions.

Evidence:

```text
Cuti upload validation: CutiController.php:144,345
Cuti storage/deletion: 235-240,438-448,466-468
Izin upload validation: IzinController.php:198
Pegawai photo validation: PegawaiController.php:162-171
Pegawai photo storage/deletion: 174-187
```

Gaps:

- Cuti/Izin file validation duplicated; no shared `DocumentUploadService`.
- No discovered upload feature tests.
- Filenames use UUID, good. Storage under public disk; PDF/images become publicly accessible if path known. Decide if documents should require auth download instead.

### P3 — Code quality: legacy comments, duplicate permission entries, unreachable logging, noisy seeders

There are many iterative comments and duplicate entries from prior development. Some code is unreachable or noisy.

Evidence:

```text
PegawaiController.php:93 Log::info after return; unreachable
PegawaiController.php:10 comment "Add this line"
routes/web.php:33,38,70,80,91-95 comments from iterative edits
Cuti.php:28,53-54,97,103 comments from iterative edits
UserRolePermissionSeeder.php:42-47 duplicate izin/verifikasi permissions
UserRolePermissionSeeder.php:149-158 repeated permissions for atasan-pimpinan
UserRolePermissionSeeder.php:160-169 repeated permissions for pimpinan
UserRolePermissionSeeder.php:58-136 extensive debug logging during seed
User.php:74-79 commented old getLoginField()
```

Impact: low runtime risk, medium maintainability risk.

### P3 — Performance: most obvious list queries eager-load, but repeated NIP lookups and balance queries remain

Cuti and Izin index/show usually use eager loading for `pegawai`, `verifikator`, `pimpinan`, etc. However, controller methods repeatedly resolve current user's `Pegawai` by NIP and repeatedly query balances.

Evidence:

```text
Cuti index eager load: CutiController.php:37,44
Izin index eager load: IzinController.php:68,73,84,95
Izin PERMA index eager load: IzinController.php:504,533
Repeated current-user Pegawai lookups: IzinController.php:71,82,93,510,515,539,544,549
Cuti ensureCutiBalance called in 10 locations
CutiBalance direct where queries in controller: 8 occurrences by scan
CutiBalance bulk updater exists: CutiBalance.php:106-146
```

Recommended:

- Add `User::pegawai()` relation or `CurrentPegawaiResolver` service.
- Use one reusable role-scoped query builder for Cuti/Izin indexes.
- Move balance retrieval into `CutiBalanceService` and cache per request where appropriate.

## Tech Debt Rankings

| Rank | Debt | Severity | Evidence | Why it matters |
|------|------|----------|----------|----------------|
| 1 | New Composer security advisories | P0 | `composer audit` current fail | Production security/regression risk |
| 2 | Fat CutiController | P1 | `CutiController.php:17-788` | Core leave rules hard to change safely |
| 3 | Fat/scattered Izin PERMA rules | P1 | `IzinController.php:22-558`, `IzinPolicy.php:97-101` | Regulatory logic can drift |
| 4 | Missing policies for non-Cuti/Izin domains | P1 | only 2 policy files | PII/admin actions rely only on middleware |
| 5 | Route map legacy/generated names | P1 | `routes/web.php:44-68`, route:list `generated::...` | Fragile route/cache/tests/navigation |
| 6 | Controller workflow test gaps | P2 | no discovered Cuti/Izin workflow feature tests | Refactors risky without integration coverage |
| 7 | View duplication in Cuti/Izin | P2 | largest Blade files 289/275/233/212/202 lines | UI changes prone to copy-paste drift |
| 8 | Docker mode ambiguity | P2 | `docker-compose.*`, `compose.*`, no Docker CI | Deploy behavior not continuously verified |
| 9 | CSP/HSTS hardening incomplete | P2 | `SecureHeadersMiddleware.php:24-34` | Security headers present but permissive |
| 10 | Seeder/debug/code hygiene | P3 | seeder duplicate perms/logging, unreachable log | Maintainability/noise |
| 11 | Repeated current-user Pegawai and balance lookups | P3 | repeated NIP lookup + balance scans | Performance + duplication |

## Recommended Next Steps (Tracks A-D)

### Track A — Security + dependency stabilization (do first)

Goal: restore true `composer audit` clean state and keep tests green.

1. Update vulnerable dependencies within Laravel 10 compatibility.
2. Replace/resolve abandoned `php-http/message-factory` if possible.
3. Run:

```bash
rm -f bootstrap/cache/config.php
composer update laravel/framework symfony/* --with-all-dependencies
php artisan test
./vendor/bin/pint --test
composer audit --no-interaction
php artisan route:cache
```

Acceptance:

```text
php artisan test PASS
Pint PASS
composer audit 0 vulnerabilities or documented accepted exceptions
route:cache PASS
```

### Track B — Cuti architecture extraction with tests

Goal: reduce 789-line controller risk without changing behavior.

Order:

1. Add Cuti workflow feature tests first:
   - annual balance submit/approve/reject rollback
   - Cuti Besar 5-year/max/mutual exclusion
   - assigned approver enforcement
   - PDF no_surat/status authorization
2. Extract `CutiBalanceService` from `ensureCutiBalance()` and balance transitions.
3. Extract `CutiEligibilityService` for Cuti Tahunan/Cuti Besar rules.
4. Extract `CutiDocumentService` for upload/delete.
5. Add FormRequests.

Acceptance:

```text
CutiController materially smaller
No Cuti behavior change unless tests/spec explicitly updated
Cuti feature + policy tests PASS
```

### Track C — Izin/PERMA domain consolidation

Goal: make PERMA No. 7/2016 rules canonical and testable outside controller.

Order:

1. Create `IzinType` config/enum-like class with jenis, approval levels, max days, time requirements, PDF template.
2. Replace duplicated jenis arrays in `IzinController.php:117-128` and `159-170`.
3. Extract `IzinApprovalService` and `IzinQueryService`.
4. Add tests for two-level and single-level status transitions, PDF template selection, and role-scoped index visibility.
5. Visual/manual review PDF templates against Lampiran II/III.

Acceptance:

```text
Single source of truth for jenis_izin
PERMA feature tests PASS
IzinPolicyTest PASS
No controller-private regulatory rules left except delegation calls
```

### Track D — Platform hardening: routes, policies, views, Docker, performance

Goal: reduce operational/maintenance risk after core workflows are safe.

1. Add policies or route-middleware matrix tests for Pegawai, HariLibur, User, Role, Permission.
2. Clean route definitions:
   - name manual routes explicitly
   - remove duplicate-ish manual resource overlaps where safe
   - preserve fixed-before-parameter order
   - always verify `php artisan route:cache`
3. Extract Blade partials for Cuti/Izin forms, tables, approval panels, navigation.
4. Add Docker CI smoke:
   - build Apache dev image or production PHP-FPM/Nginx images
   - run `php -v`, `composer install`, `php artisan config:cache`, maybe health endpoint
5. Harden security headers:
   - env-gate HSTS
   - plan CSP nonce/SRI, reduce `unsafe-eval` if possible
6. Add `CurrentPegawaiResolver`/`User::pegawai()` relationship to reduce repeated NIP lookups.

Acceptance:

```text
route:cache PASS
view/cache/build PASS
Docker smoke PASS
policy/middleware matrix tests PASS
```

## Open Questions

1. Should documents under `public/dokumen/cuti` and `public/dokumen/izin` remain publicly reachable by guessed filename, or should downloads be auth-gated?
2. Should `Izin Keluar Kantor` truly require same-day submission only, or should future-day requests be allowed if they are for a single date?
3. Should weekends be encoded in `hari_libur` data, or should `WorkdayService` inherently exclude Saturday/Sunday? Current tests passed, but source code only excludes dates returned by `HariLibur::getHariLiburByDateRange()`.
4. Which Docker mode is canonical for production: `compose.prod.yaml` or manual Dockerfiles described by README/skill?
5. Should `Pegawai` PII access be record-scoped or only role/permission scoped?
