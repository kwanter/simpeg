---
goal: Close the spec-contract gaps, restore record integrity, and harden test efficacy found by the two-axis review of the Izin controller refactor
version: 1.0
date_created: 2026-09-24
last_updated: 2026-09-24
status: Planned
tags: ["refactor", "clean-code", "architecture", "security", "tdd"]
---

# Refactoring & Remediation Plan: Izin Submission Refactor Follow-up

## 1. Traceability: Requirements & Constraints

- **SPEC-01 / REQ-005 / Spec §4.2:** the No-Surat Update predicate MUST be evaluated at most once per request. The current implementation memoizes only the database resolution, so `isNoSuratUpdate()` runs twice (`rules()` and the controller).
- **COV-01 / AC-006:** the "no `izin` row is created" half of the tidak-masuk rejection criterion has no assertion anywhere.
- **COV-02 / AC-007:** the "stored row is unchanged" half of the full-update rejection criterion has no assertion anywhere.
- **SPEC-02 / AC-001 / CON-006:** the criteria are written against "pre-existing" test files without defining that term, so they cannot be judged against a fixed point.
- **HON-01 / HON-02:** the plan's §4.0 ledger is declared authoritative yet currently states two falsehoods.
- **EC-02 / Spec §7:** `CONTEXT.md`, `docs/adr/`, and two audit reports exist on disk but are untracked, so the committed deliverable set is inverted.
- **TST-01 / AC-021:** the query-count assertion must be able to fail when zero selects are observed, not only when more than one is.
- **TST-02:** three structural assertions read whole-file substrings and are therefore coupled to comments and formatting.
- **TEST-003 / CONSTRAINTS.md §3:** no suppressions, no skipped tests, no weakened assertions may be introduced by any remediation task.
- **PRN-001:** every change in this plan is a follow-up to an already-approved, already-shipped refactor. No behaviour may change except where a criterion above explicitly requires it.

## 2. Implementation Steps

> **⚠️ EXECUTION DIRECTIVE FOR AI AGENTS (`/tdd-write-code`):**
> Execute this plan phase by phase through strict TDD. Write the failing test (RED) first, implement (GREEN), verify the full suite, then **STOP AND WAIT** for user approval before the next phase. Do not modify an inherited characterization test file in `tests/**/*Izin*`; a task that appears to need one must instead extend a refactor-owned file.

### Implementation Phase 1: Spec-Contract Gaps (the only production change)

- **GOAL-001:** Make the code satisfy the two contract clauses it currently violates, and close the two half-covered criteria.

| Task ID  | Description (Exact File Paths & TDD Steps) | Ref ID | Completed | Date |
| -------- | ------------------------------------------ | ------ | :-------: | :--: |
| TASK-101 | **Step 1 (RED):** Add `test_is_no_surat_update_is_cached_for_the_request_lifetime` to `tests/Feature/IzinNoSuratBoundaryTest.php`. Build a request against an approved Izin, call `isNoSuratUpdate()` (expects true), then `merge()` a payload that would flip the predicate (drop `no_surat_izin`, or exceed three entries), and assert the second call still returns the first answer. Fails today because the predicate is recomputed on every call. | SPEC-01 | [ ] | |
| TASK-102 | **Step 2 (GREEN):** Add a `private ?bool $noSuratUpdate = null;` cache in `app/Http/Requests/UpdateIzinRequest.php` and have `isNoSuratUpdate()` return the cached value when set. Then have `IzinController::update()` consume the same cached value rather than calling the predicate again. | SPEC-01 | [ ] | |
| TASK-103 | **Step 1 (RED):** Add the missing halves to the two criteria using **refactor-owned** files, not the inherited ones: in `tests/Feature/IzinValidationRulesTest.php` (already refactor-created) add a store-seam test asserting that an over-cap tidak-masuk payload raises `tanggal_selesai` with the exact message `Izin tidak masuk kerja maksimal 2 (dua) hari kerja.` **and** `assertSame(0, Izin::count())`; and an update-seam test asserting the same message plus a before/after snapshot of the stored row. | COV-01, COV-02 | [ ] | |
| TASK-104 | **Step 2 (GREEN):** No production change is expected. If the new assertions fail, that is a real defect and must be fixed rather than relaxed. | COV-01, COV-02 | [ ] | |
| TASK-105 | **Step 3 (RED/GREEN):** Tighten the AC-021 collector: change both `assertLessThanOrEqual(1, $lookups)` calls in `tests/Feature/IzinNoSuratBoundaryTest.php` to `assertSame(1, $lookups)`, and re-run. A failure proves the regex is not matching the resolution and the assertion was previously vacuous. | TST-01 | [ ] | |
| TASK-10X | **VERIFY:** Run `php artisan test --filter='IzinNoSuratBoundaryTest\|IzinValidationRulesTest\|IzinSubmissionServiceTest'`, then full `php artisan test`, then `./vendor/bin/pint --test`. Expect zero regressions and zero new suppressions. | - | [ ] | |
| TASK-10Y | **APPROVAL:** 🛑 Wait for explicit user confirmation to proceed to Phase 2. | - | [ ] | |

### Implementation Phase 2: Record and Artifact Integrity

- **GOAL-002:** Make every authoritative record true, and bring the deliverable set under version control.

| Task ID  | Description (Exact File Paths & TDD Steps) | Ref ID | Completed | Date |
| -------- | ------------------------------------------ | ------ | :-------: | :--: |
| TASK-201 | Correct `plan/plan-izin-controller-refactor.md` §4.0: T-008, T-009, and T-010 are committed in `a69b6c7`, not uncommitted; T-011 is executed and awaiting sign-off. | HON-01 | [ ] | |
| TASK-202 | Correct the §4.0 caveat: `git diff --stat 27c1467 -- tests/` lists **four** refactor-owned files, not three — `IzinEditDecisionEquivalenceTest.php` was omitted. | HON-02 | [ ] | |
| TASK-203 | Extend `RBCK-001` to record the second shared commit (`a69b6c7` covering T-008/009/010) and relax the rule from "one commit per ticket" to "one commit per coherent change set", since the file-interleaving case has now occurred twice. | HON-01 | [ ] | |
| TASK-204 | Track the untracked deliverables: `CONTEXT.md`, `docs/adr/0001-izin-no-surat-update-discrimination.md`, `docs/audit/clarification-report-izin-controller-refactor-2026-09-24.md`, and `docs/audit/consistency-audit-izin-controller-refactor-2026-09-24.md`. Commit them together as the governance baseline. | EC-02 | [ ] | |
| TASK-205 | Add `phpunit.xml` and the phase-3 audit report to Spec §7's file lists, or state explicitly why the test-config hardening is out of scope. | ORPH-01, ORPH-02 | [ ] | |
| TASK-206 | Spec §5.1: redefine AC-001 and CON-006 to distinguish an **inherited oracle file** (never modified) from a **refactor-owned file** (§6.4 files may grow as tickets add assertions). State the baseline commit that defines "inherited". | SPEC-02 | [ ] | |
| TASK-20X | **VERIFY:** Re-run `git diff --stat 27c1467 -- tests/` and confirm the enumerated set matches the ledger; confirm `git status --short` reports no untracked file that Spec §7 lists as a deliverable. | - | [ ] | |
| TASK-20Y | **APPROVAL:** 🛑 Wait for explicit user confirmation to proceed to Phase 3. | - | [ ] | |

**Phase 2 status (2026-09-24):** TASK-201, TASK-202, and TASK-203 are **complete**, verified by re-reading `git log` for all nine commit hashes the ledger claims. TASK-204 is **ready but uncommitted** — the four untracked §7 deliverables were confirmed present with `git status --short`. TASK-205 and TASK-206 are **deferred to `/tdd-spec`**: both edit `spec/spec-feature-izin-controller-refactor.md`, and a Developer must not redefine an acceptance criterion. TASK-206 is the more consequential of the two, because it changes what AC-001 and CON-006 *mean*, which is a requirements decision rather than an implementation detail.

### Implementation Phase 3: Test-Efficacy Hardening

- **GOAL-003:** Remove the assertions that cannot fail, or that fail for the wrong reason.

| Task ID  | Description (Exact File Paths & TDD Steps) | Ref ID | Completed | Date |
| -------- | ------------------------------------------ | ------ | :-------: | :--: |
| TASK-301 | Replace the whole-file symbol greps in `tests/Unit/IzinStructureTest.php` (`WorkdayService`, `isSingleLevel`, `maxWorkdays`) with behavioural or reflection-scoped assertions: the `controllerMethodSource()` helper already exists, so scope the check to `store()` and `update()`, and replace the controller-wide grep with a behavioural assertion (e.g. a single-level store request asserting `jumlah_hari = 0` and pinned dates). Keep the `ReflectionMethod::getFileName()` assertions — they are the robust pattern. | TST-02 | [ ] | |
| TASK-302 | Replace the probabilistic UUID check in `tests/Feature/IzinContractPinTest.php` with a deterministic one: keep the v4 regex and pin the generator, or assert the value is not an ordered UUID by construction rather than by distance from wall-clock. | TST-03 | [ ] | |
| TASK-303 | Drop the no-op `assertTrue(true)` in `tests/Unit/Services/IzinDocumentServiceTest.php:141` and relax the exact-public-method pin in `tests/Unit/Services/IzinSubmissionServiceTest.php` to `assertContains` for the three contract methods, so a harmless addition does not redden it. | TST-04 | [ ] | |
| TASK-30X | **VERIFY:** Full suite plus Pint; prove each rewritten assertion still bites by a temporary mutation and restore. | - | [ ] | |
| TASK-30Y | **APPROVAL:** 🛑 Wait for explicit user confirmation to proceed to Phase 4. | - | [ ] | |

### Implementation Phase 4: Optional Quality Work (Non-Blocking)

- **GOAL-004:** Address the optional findings without expanding scope. Each task is independently droppable.

| Task ID  | Description (Exact File Paths & TDD Steps) | Ref ID | Completed | Date |
| -------- | ------------------------------------------ | ------ | :-------: | :--: |
| TASK-401 | Add an explicit non-null accessor (`resolvedIzinOrFail()`) or a one-line guard in `IzinController::update()` so the 404 contract is enforced at the call site rather than inherited from the request layer. | COR-01 | [ ] | |
| TASK-402 | Extract the nine duplicated base rule lines into `baseRules()` on `JenisIzinRulesTrait`, leaving each request to merge only its delta. Re-run the 23 PERMA pins to prove rule-set equivalence. | ARCH-01 | [ ] | |
| TASK-403 | Move the `WorkdayService::countWorkdays()` call in `IzinSubmissionService::normalizeJenis()` into the branch that needs it, so the single-level path stops issuing a discarded `HariLibur` query. | PERF-02 | [ ] | |
| TASK-404 | Split the merged PERMA citation in `JenisIzinRulesTrait::jenisRules()` so each branch carries its own reference, and add an intent docblock to `messages()`. | RD-01 | [ ] | |
| TASK-405 | Correct the inaccurate comment on `app/Models/Izin.php:21`; consider removing `status` and `verifikasi_*` from `$fillable` after confirming the no-surat path does not rely on them. | SEC-01 | [ ] | |
| TASK-40X | **VERIFY:** Full suite, Pint, and zero suppressions. | - | [ ] | |

## 3. Structural Remedies & Alternatives

- **ALT-001 (rejected for Phase 1):** delete the controller's residual `isNoSuratUpdate()` call and rely solely on the cached flag set during validation. Rejected as the primary remedy because it makes the controller depend on a side effect of validation; caching inside the predicate keeps the contract local and keeps `rules()` and the action reading the same value.
- **ALT-002 (rejected):** relax REQ-005 to "the resolution is evaluated at most once" instead of memoizing the predicate. Rejected because the spec's §4.2 explicitly commits to predicate memoization, and because a second evaluation is the mechanism by which the no-surat boundary could drift if the payload were ever mutated mid-request.
- **ALT-003 (rejected):** move the three inherited assertions from COV-01/COV-02 into the inherited `IzinPermaValidationTest`. Rejected by CON-006 and AC-001: inherited oracle files are not to be touched.

## 4. Files Affected

- **FILE-001:** `app/Http/Requests/UpdateIzinRequest.php` (predicate cache)
- **FILE-002:** `app/Http/Controllers/IzinController.php` (consume cached flag; Phase 4 optional guard)
- **FILE-003:** `tests/Feature/IzinNoSuratBoundaryTest.php` (memoization test; tightened bound)
- **FILE-004:** `tests/Feature/IzinValidationRulesTest.php` (COV-01, COV-02 halves)
- **FILE-005:** `tests/Unit/IzinStructureTest.php` (TST-02)
- **FILE-006:** `tests/Feature/IzinContractPinTest.php` (TST-03)
- **FILE-007:** `plan/plan-izin-controller-refactor.md` (HON-01, HON-02, RBCK-001)
- **FILE-008:** `spec/spec-feature-izin-controller-refactor.md` (§5.1, §7)
- **FILE-009:** `CONTEXT.md`, `docs/adr/0001-izin-no-surat-update-discrimination.md`, and the two `docs/audit/*` reports (track them)
- **FILE-010 (Phase 4 only):** `app/Traits/JenisIzinRulesTrait.php`, `app/Services/IzinSubmissionService.php`, `app/Models/Izin.php`, `tests/Unit/Services/IzinSubmissionServiceTest.php`, `tests/Unit/Services/IzinDocumentServiceTest.php`

## 5. Rollback / Recovery Plan

- **RBCK-001:** Phase 1's only production change is the predicate cache in `UpdateIzinRequest`. If it regresses anything, `git revert` that single commit; the memoization of `resolvedIzin()` is independent of it, so AC-021 and the 404 contract survive a revert.
- **RBCK-002:** Phase 2 changes no production code. If the ledger or spec edit is wrong, restore the file from git; nothing downstream depends on it.
- **RBCK-003:** Phase 3 rewrites assertions. Each rewritten assertion must be proven to bite by a temporary mutation before the task is marked complete; if a rewrite cannot be made to fail, revert the rewrite rather than weaken it.
- **RBCK-004:** Phase 4 tasks are independent and droppable. TASK-402 (base rules) and TASK-405 (fillable) are the two that touch validation and mass-assignment respectively; each must be reverted on any red PERMA pin or mass-assignment test.
- **RBCK-005:** No task in this plan may modify a file in `tests/**/*Izin*` that existed before the refactor baseline. If a task appears to require it, stop and escalate to `/tdd-spec`.
