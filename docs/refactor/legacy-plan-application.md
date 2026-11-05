# Legacy Characterization and Refactor Plan: SIMPEG Application

## 1. Target Legacy Component

- **Scope:** Existing application code under `app/`, together with behavior exposed through routes, models, scheduled commands, and user-facing views.
- **Current Test Coverage:** The current PHPUnit suite contains 363 passing tests and 979 assertions as of 2026-09-24. It stood at 358 tests and 964 assertions before this model-characterization batch; the growth includes this batch and previously added refactor-owned pins. These counts are not line-coverage measurements; multiple application classes and behavior areas have no direct focused test identified in the current suite.
- **Baseline Command:** `php artisan test`
- **Baseline Status:** PASSING (363 tests, 979 assertions and `./vendor/bin/pint --test` clean across 201 files, verified 2026-09-24).
- **Architecture:** Laravel MVC application. Existing public seams include named routes, HTTP controllers, form requests, Eloquent models, policies, application services, Artisan commands, and Blade-rendered responses.
- **Constraints:** Preserve currently observable behavior, including odd outputs and legacy compatibility. Do not change production code until the relevant characterization tests pass. Run the full applicable suite after each batch.

## 2. Characterization Test Suite (Pinning Phase)

### Completed Batch

- **Target File:** `app/Models/Izin.php` persistence and relationship contracts
- **Test File:** `tests/Unit/Models/IzinModelTest.php`
- **Captured Scenarios:** UUID is the persisted non-incrementing string primary key; `pegawai`, `atasan_pimpinan`, and `pimpinan` resolve through their UUID foreign keys; nullable approver UUIDs resolve to null; request and approval date attributes hydrate as date values.
- **Status:** PASSING (4 tests, 15 assertions).
- **Verification:** Focused `php artisan test --filter=IzinModelTest` and focused Pint pass. Full suite/Pint rerun after this batch is still pending.

- **Target File:** `app/Services/IzinDocumentService.php`
- **Test File:** `tests/Unit/Services/IzinDocumentServiceTest.php`
- **Captured Scenarios:** MIME-derived extensions; private-disk storage; unsupported MIME rejection; storage failure; private and legacy public disk deletion/existence/download; traversal rejection; missing-file response; private-over-legacy download precedence; normalized cache-control header.
- **Status:** PASSING (11 tests, 22 assertions).
- **Verification:** `php artisan test --filter=IzinDocumentServiceTest`, `./vendor/bin/pint --test tests/Unit/Services/IzinDocumentServiceTest.php`, and full `php artisan test` all pass.
- **Observed Runtime Detail:** Symfony normalizes the download cache-control header to `no-store, private`; the characterization assertion records this exact response.

- **Target File:** `app/Http/Controllers/IzinController.php` delete route
- **Test File:** `tests/Feature/IzinLifecycleTest.php`
- **Captured Scenarios:** Owner deletion removes the Izin record and matching private/legacy documents; a non-owner receives 403 while the record and private document remain.
- **Status:** PASSING (2 tests, 9 assertions; included in the lifecycle test file's 5 passing tests and 20 assertions).
- **Verification:** `php artisan test --filter=IzinLifecycleTest` and `./vendor/bin/pint --test tests/Feature/IzinLifecycleTest.php` pass.

- **Target File:** `app/Http/Controllers/IzinController.php` edit route and `resources/views/izin/edit.blade.php`
- **Test File:** `tests/Feature/IzinLifecycleTest.php`
- **Captured Scenarios:** Owner can open the regular edit form while both approval fields are unverified; owner is forbidden after atasan approval even when the letter number is missing; admin can open the number-letter form after atasan approval, where current HTML contains a placeholder full-form branch but no submit button or `jenis_izin` field.
- **Status:** PASSING (3 tests, 14 assertions; included in the lifecycle test file's 8 passing tests and 34 assertions).
- **Observed Legacy Quirk:** The admin GET form is a placeholder in this state and exposes no submission control. The behavior is pinned as-is; no production change was made.
- **Verification:** `php artisan test --filter=IzinLifecycleTest` and `./vendor/bin/pint --test tests/Feature/IzinLifecycleTest.php` pass.

- **Target File:** `app/Http/Controllers/IzinController.php` create/store and update routes
- **Test File:** `tests/Feature/IzinLifecycleTest.php`
- **Captured Scenarios:** Store forces the authenticated Pegawai UUID despite a supplied different UUID, calculates workdays, and initializes status/verification fields regardless of supplied state fields; regular update recalculates workdays and ignores unvalidated state fields; admin no-surat update changes only the document number and retains existing approval state and reason.
- **Status:** PASSING (3 tests, 11 assertions).
- **Verification:** `php artisan test --filter=IzinLifecycleTest` and `./vendor/bin/pint --test tests/Feature/IzinLifecycleTest.php` pass.

- **Target File:** `app/Http/Controllers/IzinController.php` category index routes: `indexKeluarKantor()` and `indexTidakMasuk()`
- **Test File:** `tests/Feature/IzinCategoryRoutesTest.php`
- **Captured Scenarios:** The keluar-kantor view contains only the owner's single-level izin types; the tidak-masuk view contains only the owner's izin tidak masuk records; unrelated types and another employee's matching records are excluded.
- **Status:** PASSING (2 tests, 6 assertions).
- **Verification:** `php artisan test --filter=IzinCategoryRoutesTest` passes. UUID collections are compared without relying on incidental ordering when `latest()` rows have equal timestamps.

- **Target File:** `app/Http/Controllers/IzinController.php` approval POST routes
- **Test File:** `tests/Feature/IzinApprovalEndpointsTest.php`
- **Captured Scenarios:** Assigned atasan approves through the HTTP route and persists status/note with the controller success flash; assigned pimpinan approves after atasan and persists the final status/note; invalid atasan decisions return validation errors without state changes; an unassigned atasan receives 403 without changing the Izin. Assigned approvers receive the expected GET pages/forms, pimpinan sees the atasan decision/note, and the pimpinan page is forbidden before atasan approval.
- **Status:** PASSING (7 tests, 26 assertions).
- **Flake Fixed (determinism defect in this batch):** Two tests originally relied on the factory's random `jenis_izin`, which can select a single-level jenis (`Izin Pulang Cepat`). Because `IzinPolicy::verifyPimpinan` returns false for single-level jenis and `IzinApprovalService::applyAtasan` sets the final `Disetujui` status for them, the suite failed intermittently (observed in roughly 1 of 8 full-suite runs) with a 403 or an unexpected status. Both tests now pin `jenis_izin` to a two-level value, matching the explicit pinning already used by `IzinApprovalRaceTest` and `IzinApprovalServiceTest`. Verified stable across 20 consecutive class runs and 4 consecutive full-suite runs. No production code was involved; the underlying application behaviour was already correct and deterministic.
- **Verification:** `php artisan test --filter=IzinApprovalEndpointsTest` and `./vendor/bin/pint --test tests/Feature/IzinApprovalEndpointsTest.php` pass.

- **Target File:** `app/Services/IzinApprovalService.php`
- **Test File:** `tests/Unit/Services/IzinApprovalServiceTest.php`
- **Captured Scenarios:** Unsupported decisions are rejected by SQLite constraints with transactional rollback at both approval levels; approval timestamps are returned with date-only model casting; invalid current status returns the exact validation error payload and leaves the stored state unchanged.
- **Status:** PASSING (4 tests, 9 assertions).
- **Verification:** `php artisan test --filter=IzinApprovalServiceTest` and `./vendor/bin/pint --test tests/Unit/Services/IzinApprovalServiceTest.php` pass. Existing `IzinApprovalRaceTest` remains the regression coverage for normal approval/rejection transitions.

- **Target File:** `app/Http/Controllers/IzinController.php` document download route, plus `IzinDocumentService` legacy-disk fallback
- **Test File:** `tests/Feature/IzinDocumentAuthorizationTest.php`
- **Captured Scenarios:** The owner can download a document that exists only on the legacy `public` disk and receives the same normalized `no-store, private` cache-control header; the assigned atasan can download the private-disk document; a missing `dokumen` value and a `dokumen` value with no backing file both return 404.
- **Status:** PASSING (4 tests, 9 assertions).
- **Observed Runtime Detail:** The legacy `public` disk fallback is reachable through the HTTP route, not only through the service API, so the fallback is pinned at the route seam as well.
- **Verification:** `php artisan test --filter=IzinDocumentAuthorizationTest` and `./vendor/bin/pint --test tests/Feature/IzinDocumentAuthorizationTest.php` pass.

- **Infrastructure Note:** `phpunit.xml` was switched from `<env>` entries to `<server ... force="true"/>` entries so the SQLite `:memory:` testing configuration cannot be overridden by an inherited shell environment. No application code was affected.

- **Target File:** `app/Http/Requests/StoreIzinRequest.php` and `app/Http/Requests/UpdateIzinRequest.php` validation rules
- **Test File:** `tests/Feature/IzinValidationRulesTest.php`
- **Captured Scenarios:** A `jenis_izin` value outside `IzinType::all()` is rejected on both store and update; `tanggal_selesai` before `tanggal_mulai` is rejected for a two-level jenis; missing `atasan_pimpinan_uuid` and `pimpinan_uuid` produce `required` errors; an unknown approver UUID produces an `exists` error; single-level jenis without `jam_mulai`/`jam_selesai` produces `required` errors; single-level jenis on a non-today date returns the pinned Indonesian `date_equals` message; `alasan` over 500 characters is rejected for single-level and tidak-masuk jenis; tidak-masuk `tanggal_mulai` in the past is rejected; unsupported `dokumen` MIME is rejected and a valid PDF is stored on the private disk; a duplicate `no_surat_izin` in the no-surat update path is rejected.
- **Status:** PASSING (14 tests, 44 assertions).
- **Observed Legacy Quirk:** The base (two-level, non-tidak-masuk) rule set declares `alasan` as `required,string` with no length cap, so a 900-character `alasan` is accepted for `Izin Sakit` even though the single-level and tidak-masuk branches enforce `max:500`. Pinned as-is; no production change was made.
- **Verification:** `php artisan test --filter=IzinValidationRulesTest`, `./vendor/bin/pint --test tests/Feature/IzinValidationRulesTest.php`, and full `php artisan test` pass.

- **Target File:** `app/Http/Controllers/IzinController.php` full-update document handling
- **Test File:** `tests/Feature/IzinDocumentLifecycleTest.php`
- **Captured Scenarios:** A full update that carries a file stores the replacement, rebinds `dokumen` to the new UUID filename, and deletes the previous file from the private disk; the previous file is also removed from the legacy `public` disk when it lived there; a full update without a file key keeps the stored `dokumen` value and leaves the file in place; a full update on an Izin with no previous document stores the new file without attempting a deletion; an unsupported document type is rejected with the request-level message `The dokumen field must be a file of type: pdf, jpg, jpeg, png.` and the existing document is left untouched.
- **Status:** PASSING (5 tests, 21 assertions).
- **Observed Runtime Detail:** The request rules reject unsupported `dokumen` values before the controller runs, so `IzinDocumentService`'s own `Tipe dokumen tidak didukung.` branch is not reachable through the HTTP seam for these MIME types. That leaves the controller's post-store cleanup path (`$izin->update()` throwing after a successful store) without a controllable HTTP trigger; it remains inventoried but unpinned and is noted in the backlog.
- **Verification:** `php artisan test --filter=IzinDocumentLifecycleTest` and `./vendor/bin/pint --test tests/Feature/IzinDocumentLifecycleTest.php` pass.

- **Target File:** `app/Http/Controllers/IzinController.php` store `pegawai_uuid` override branches
- **Test File:** `tests/Feature/IzinOwnershipAssignmentTest.php`
- **Captured Scenarios:** An admin who supplies another Pegawai's UUID creates the Izin for that Pegawai and not for their own; an admin who omits `pegawai_uuid` is bound to their own Pegawai; an admin who sends an explicit `null` is also bound to their own Pegawai; an unknown `pegawai_uuid` is rejected by the `exists` rule without creating a record.
- **Status:** PASSING (4 tests, 13 assertions).
- **Note:** The non-admin override branch (a non-admin supplying another Pegawai's UUID is forced back to their own) was already pinned by `IzinLifecycleTest::test_store_binds_izin_to_authenticated_pegawai_and_initializes_approval_state` and is not duplicated here.
- **Verification:** `php artisan test --filter=IzinOwnershipAssignmentTest` and `./vendor/bin/pint --test tests/Feature/IzinOwnershipAssignmentTest.php` pass.

### Existing Coverage to Reuse

Do not duplicate already-tested behavior without a specific unpinned scenario. Current focused coverage includes:

- `CutiApprovalService`, `CutiBalanceService`, `CutiEligibilityService`, `CutiDocumentService`, `IzinQueryService`, `ApproverDirectoryService`, and `WorkdayService`.
- `CutiPolicy`, `IzinPolicy`, `SafeEmail`, `CutiType`, `IzinType`, and `SecureHeadersMiddleware`.
- Izin approval transitions and race-related status guards, through `IzinApprovalRaceTest`.
- Authentication, profile, document authorization, leave/permit validation, role middleware, navigation visibility, user privileges/provisioning, and permission seeder consistency through feature tests.
- Izin PERMA workday and same-day rules through `IzinPermaValidationTest`; the remaining `StoreIzinRequest`/`UpdateIzinRequest` rule branches through `IzinValidationRulesTest`; full-update document replacement/removal through `IzinDocumentLifecycleTest`; and store ownership assignment through `IzinOwnershipAssignmentTest`.
- `PrivatizeHrDocuments` through `PrivatizeHrDocumentsCommandTest`.

### Test Determinism Rules

`IzinFactory` randomises `jenis_izin` across a mix of single-level (`Izin Pulang Cepat`) and two-level jenis. Any test whose expectations depend on the approval depth — the number of verification steps, the resulting `status` value, or pimpinan authorization — MUST pin `jenis_izin` explicitly. `IzinApprovalRaceTest` and `IzinApprovalServiceTest` already follow this rule; the `IzinApprovalEndpointsTest` flake recorded above is the case that did not.

## 3. Remaining Characterization Backlog

The following are candidates for focused behavior inventory. Confirm coverage and scenarios immediately before each batch; an indirect feature test may already pin a public behavior even where no class-named test exists.

1. **Izin application workflow:** Document download edge cases and PDF rendering for any future Izin types; all current Izin PDF templates (default, shared single-level, and tidak-masuk), PDF authorization, document download legacy-disk/approver/404 cases, edit route/view, create/store, regular update, no-surat update, delete, category index, approval GET/POST routes, `IzinApprovalService` edge cases, `StoreIzinRequest`/`UpdateIzinRequest` validation rule branches, full-update document replacement/removal, and `pegawai_uuid` ownership assignment are characterized above. Remaining candidates: the remaining `Izin` model persistence contracts, and the controller's post-store document cleanup path when `$izin->update()` throws (no controllable HTTP trigger exists — candidate for service-level fault injection rather than a route seam).
2. **Cuti HTTP workflow:** `CutiController` CRUD, request branches, balance endpoints, PDF generation, and document service legacy behavior not currently pinned.
3. **Organization and employee CRUD:** `PegawaiController`, `UserController`, `JabatanController`, `HariLiburController`, `RoleController`, `PermissionController`, and both history controllers; prioritize observable authorization, validation, persistence, and redirects.
4. **Domain models and persistence behavior:** `Cuti`, `CutiBalance`, `Izin`, `Pegawai`, `Jabatan`, `HariLibur`, `RiwayatJabatan`, `RiwayatPangkat`, `Role`, `Permission`, and `User` behavior not covered by current feature/service tests.
5. **Cross-cutting application code:** `UserTrackingTrait`, custom request authorization/rules, middleware variants, exception rendering, providers, and layout/view component behavior where it differs from Laravel defaults.
6. **Scheduled and operational entry points:** `UpdateCutiBalances`, `Console\Kernel`, scheduled task registration, and relevant command failure/idempotency outcomes.
7. **Browser-visible workflows:** Extend beyond the current guest-facing Playwright baseline only after corresponding HTTP behavior is characterized and test accounts/environment are defined.
8. **Persistence history:** Review migrations, factories, and seeders for compatibility contracts, rollback behavior, indexes/foreign keys, and idempotency. Schema changes or destructive migration operations are outside this plan without explicit approval.

## 4. Seam Extraction and Refactoring Plan

1. **Step 1:** Keep the application production code unchanged while selecting one bounded behavior slice from the backlog.
2. **Step 2:** Inspect the slice's source, existing feature/unit tests, relevant routes, migrations, and fixtures; record observable inputs, outputs, side effects, and error behavior.
3. **Step 3:** Add characterization tests at the narrowest stable public seam (service API, command invocation, model persistence contract, HTTP route, or rendered response).
4. **Step 4:** Run the focused characterization tests, verify they pass against current code, and run `php artisan test` plus `./vendor/bin/pint --test` for the changed PHP files.
5. **Step 5:** Update this plan with the exact tests, covered scenarios, and verification results. Repeat by bounded module; do not claim repository-wide characterization until the inventory is complete.
6. **Step 6:** Only after the chosen component's characterization suite passes 100%, hand off to `/tdd-spec` or `/tdd-plan-tasks` for its surgical refactor. Keep each refactor independently reviewable and rerun the full applicable quality gates.

## 5. Current Status and Handoff

- **Characterized:** `IzinDocumentService`, `IzinApprovalService`, Izin category index, edit form, selected lifecycle, delete, approval GET/POST endpoints, all currently mapped PDF templates, PDF authorization, document download legacy/approver/404 behaviors, the `StoreIzinRequest`/`UpdateIzinRequest` validation rule branches, full-update document replacement/removal, and `pegawai_uuid` ownership assignment described above.
- **Suite Stability:** The Izin approval-endpoint flake caused by randomised `jenis_izin` is fixed; at the time of handoff the full suite was stable across 4 consecutive runs at 320 tests and 695 assertions. The determinism rule is recorded under "Test Determinism Rules" above.
- **Not characterized repository-wide:** Remaining backlog modules and paths have not yet been exhaustively pinned.
- **Production Refactor Status:** SUPERSEDED - the Izin HTTP workflow slice was handed off and has since been refactored under `plan/plan-izin-controller-refactor.md` (tickets T-001..T-011, committed) and its follow-up `plan/plan-refactor-izin-submission-v1.0.md`. The earlier "not started" statement here described the end of the characterization phase only and is retained as history, not as current state. The working tree currently holds uncommitted follow-up work for COR-01/SEC-005, ARCH-01, PERF-02, RD-01 and SEC-01, verified green.
- **Next Action:** Continue `plan/plan-refactor-izin-submission-v1.0.md` - commit the uncommitted Phase 4 work, and settle TASK-303(b) by amending AC-003 through `/tdd-spec` before relaxing the `IzinSubmissionService` seam pin. If the effort returns to pinning, the remaining backlog starts at the Cuti HTTP workflow (item 2); the Izin model persistence contracts noted below are still unpinned.
- **Handoff Condition:** A module is eligible for refactoring only when its selected characterization suite is passing and its observable behavior inventory is recorded here.
