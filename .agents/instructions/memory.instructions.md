---
description: "Project memory file for tracking progress, active artifacts, and cross-session decisions."
---

# Project Memory: Active Context & Knowledge Base

> This file is managed by the `memory-manager` skill and `/tdd-retro`.

## 1. Project Context
- **Project Name:** (Configured via /tdd-init)
- **Current Phase:** Phase 0 (Discovery)

## 2. Active Artifacts & Documents
- `AGENTS.md`
- `CONSTITUTION.md`
- `CONSTRAINTS.md`

## 3. Session Progress Log
- Initialized TDD-Spec SDLC Architecture.

## 4. Permanent Knowledge Base & Architecture Decisions
- Strict Test-First & Pre-Agreed Seams Mandate enforced across all phases.

## 📝 Session Checkpoint: 2026-10-07

- **Active Memory Path:** `.agents/instructions/memory.instructions.md`
- **Current SDLC Phase:** Implementation / Code. Monthly PKP remediation
  phases 1 through 5 have completed their local execution gates; awaiting
  explicit completion/re-review approval (TASK-50A). This checkpoint
  supersedes the initial bootstrap's Phase 0 active-state description.
- **Active Artifacts:**
  - `spec/spec-feature-monthly-pkp.md` — Approved v0.4, unchanged.
    SHA-256:
    `512156a3937a0925a101eca9cae2e6ad5187eb38395b262fde1bdd6266418734`
  - `plan/plan-monthly-pkp.md` — Approved v1.2; evidence reconciliation
    reopens original RED/VERIFY boxes without retained packet provenance.
    Do not interpret GREEN implementation boxes as historical TDD proof.
  - `plan/plan-refactor-monthly-pkp-v1.0.md` — Approved remediation plan;
    active phase 5, TASK-501..509 checked, TASK-50A unchecked.
  - `docs/review/code-review-monthly-pkp-2026-10-06.md` — Immutable review
    baseline; conduct a fresh review rather than rewriting its findings.
  - `docs/review/monthly-pkp-ac-evidence.md` — All 90 functional criteria
    mapped to 102 realized test names; readiness 98/100, with original
    RED-provenance and unrun hosted-CI limitations explicitly retained.
  - `docs/pkp-verification.md` and `docs/ARCHITECTURE.md` — Recorded local
    runtime evidence and updated public seams/topology.
- **Achieved Milestones:**
  - Restored bound-source authorization, parser budgets, safe infrastructure
    errors, and consistent module-local JSON/HTML validation feedback.
  - Added editable notes/eligible assessor selection, correction actions,
    strict invariant decimal input, and exact decimal-string round trips.
  - Separated official/pending reads, isolated authorized immutable history,
    retained frozen lateness/deadlines, and paginated both queues at 25 rows.
  - Enabled authorized archived approved printing; application PDFs contain
    frozen notes/identity/assessor and exact activity/indicator calculations.
  - Proven independent committed-worker identity races on PostgreSQL/MySQL;
    employee fields and both period-effective histories now share one SQL
    statement. Legacy history-writing controllers were not changed.
  - Fixed 500-activity editor reload keys, raw dotted-index whitespace
    bypass, frozen timestamp precision, deleted-employee mutation denial,
    future-period business code, approved-owner edit conflict, and missing
    external-source storage rejection before first-submit capture.
  - PDF maximum text uses continuation rows and wrapped notes at unchanged
    font sizes. Real PDF coordinates/text and independent Poppler visual QA
    retain end markers, repeated headers, exact fractions, and signature.
  - User authorized the two inherited rollback repairs through continuation:
    correct rank-history down table name and preserve MySQL FK index support.
    Full baseline and additive up/down/up passed on disposable databases.
- **Recorded Verification (not rerun during this checkpoint save):**
  - Full PHP after rollback fixes: 595 tests, 4,557 assertions, 87.61 seconds.
  - Pint: all 274 PHP files passed. Build and PHP/JS syntax checks passed.
  - PKP Chromium: 12 scenarios; base Chromium: two guest scenarios.
    Normal-motion desktop/mobile form screenshots were separately inspected.
    The application logo was unavailable in the test environment.
  - PostgreSQL 16 and MySQL 8.0 concurrency: 12 tests / 104 assertions each.
  - Actual Nginx/PHP-FPM upload envelope: one test / 33 assertions; exact
    10 MiB accepted, 10 MiB + 1 byte rejected with 422, proxy oversize 413,
    with prior binding retained. qpdf 12.2.0 in FPM/Apache/workspace;
    host qpdf 12.4.2. Supported majors are >=11, not hard-coded to 12.
  - Named test stack containers verified stopped; no volumes removed.
    No production migration, hosted CI, release, staging, commit, or push.
- **Dead-Ends (Do NOT Repeat):**
  - Overlapping PHP suites collide on shared Storage::fake fixture paths;
    run affected full/focused PHP suites sequentially.
  - Installed Playwright browser revision 1208 was incomplete; use the
    existing Chrome for Testing executable through PKP_CHROMIUM_EXECUTABLE.
  - MySQL JSON reorders object members: normalize object-key order only;
    retain strict value types and indicator/activity list-order assertions.
  - Dompdf table cells cannot split across pages. Giant rows/first-header
    orphans clip text or lose repeated headers; use bounded continuation
    fragments and verify actual PDF text coordinates plus rendered pages.
  - Temporary /tmp logs and QA artifacts may disappear between sessions;
    persisted verification documents are the checkpoint reference. Rebuild
    runtime evidence when its original files are unavailable.
- **Updated Files:**
  - `app/Services/PkpIdentityService.php`, `PkpSubmissionService.php`,
    `PkpPdfService.php`, `PkpPdfValidator.php`, and related PKP services.
  - `app/Http/Controllers/PkpController.php`, PKP request/error adapter,
    `app/Http/Middleware/TrimStrings.php`, and `app/Policies/PkpPolicy.php`.
  - `resources/views/pkp/`, `e2e/pkp.spec.js`, PKP feature/unit/integration
    tests, concurrency harness, guarded E2E fixtures, and PKP integration CI.
  - `database/migrations/2025_02_12_150710_create_riwayat_pangkats_table.php`
    and `2026_02_10_004802_add_indexes_to_izin_and_cuti_tables.php` — Only
    the authorized rollback corrections; FK relationships remain intact.
  - Specification, plan, verification, review-evidence, and architecture
    paths are listed above; specification content/hash stayed unchanged.
- **Decisions Made:**
  - Preserve exact rational calculation and no-rounding semantics; UI
    previews never drive stored scores or predicates.
  - Preserve signed-external replacement rules confirmed in spec v0.4,
    immutable attempt/source evidence, and accepted-A retention until B
    inspection. Printing does not authorize archived mutation.
  - Direct PkpAuthorization remains authoritative despite a positive global
    Gate result; unrelated Cuti/Izin/provider behavior remains unchanged.
  - Preserve unrelated dirty Izin/refactor/lockfile/governance work. Do not
    reset/clean, stage everything, or infer release/commit authorization.
- **Next Action / Pending:**
  - Obtain TASK-50A completion/re-review authorization, then invoke
    /tdd-code-review with both approved specification and implementation /
    remediation plans. Continue in this chat if the user prefers.
  - Dependency audits remain non-green: Composer exited 1 with five existing
    Laravel/Flysystem advisories; npm exited 1 with nine existing findings
    (seven high, two moderate). No dependency upgrades were introduced.
    Composer repository metadata used cache after a Packagist timeout;
    freshness is partly unverified. Existing high findings require a
    separate reachable-path and pre-release disposition.
  - Do not claim hosted CI, production release, or original unretained RED
    packet provenance is verified by passing local functional tests.

<!-- checkpoint-tail:
Monthly PKP phases 1-5 passed local gates; 595 PHP tests and both engine
recovery cycles pass; re-review and dependency disposition remain pending.
-->

---

## Session Checkpoint: 2026-11-05

- **Active Memory Path:** `.agents/instructions/memory.instructions.md`
- **Current SDLC Phase:** Authorized bug fixes verified; local work committed
  and pushed. This checkpoint does not establish release readiness.
- **Active Artifacts:**
  - `spec/spec-feature-monthly-pkp.md`, `plan/plan-monthly-pkp.md`, and
    `docs/pkp-verification.md` remain the PKP reference artifacts.
  - The preceding checkpoint retains PKP review and dependency-audit risks.
- **Achieved Milestones:**
  - Started the bind-mounted preview with `docker-compose.dev.yml` at
    `http://localhost:8080`: Apache/PHP app, MySQL 8.0, and Redis.
  - Ran 31 migrations, seeded six users and 50 employees, linked demo
    accounts, configured two PKP assessors, and created a November PKP draft.
  - Exercised real HTTP Izin approvals and Cuti three-stage approvals.
    The approved annual leave consumed three days: 12 total, 3 used, 9 left.
  - Fixed all four reported defects through regression tests and live HTTP:
    PDF filename separators, Cuti letter-number-only validation, the missing
    Cuti balance redirect route, and unimplemented resource show routes.
- **Recorded Verification (not rerun during this checkpoint save):**
  - Full suite in the app container: 602 passed, 4,580 assertions.
  - Repository-wide Pint: 277 files passed.
  - Izin and Cuti PDF downloads with slashed letter numbers returned 200
    application/pdf; qpdf checks found no syntax or stream encoding errors.
  - Cuti balance without a linked employee returned 302 to `/cuti`; the
    linked employee case returned 200.
  - Users, roles, and permissions detail GET URLs returned 405 rather than
    500. Their index/edit pages and role permission editor returned 200.
  - App restart retained the Redis-backed login session and database data.
  - Browser relay failed while the Mac was locked; this session established
    HTTP/PDF evidence, not fresh browser screenshot evidence.
- **Dead-Ends (Do NOT Repeat):**
  - Host qpdf exists at `/opt/homebrew/bin/qpdf`, but configuration requires
    `PKP_QPDF_BINARY`. An unset variable caused 51 host-suite failures.
    Supplying it made the two affected PKP modules pass 37 tests with 506
    assertions; the complete container suite was green.
  - Removing a resource show route leaves PUT/PATCH/DELETE at the same path;
    GET therefore returns 405, not 404. Do not add routing workarounds.
- **Updated Files:**
  - `app/Http/Controllers/CutiController.php` and `IzinController.php` replace
    slash/backslash characters only in generated PDF download filenames.
  - `app/Http/Requests/UpdateCutiRequest.php` accepts the approved-record
    letter-number-only payload using the existing validation rules.
  - `routes/web.php` excludes show for users, roles, and permissions;
    no show callers or corresponding controller actions existed.
  - Regression coverage is in `CutiBalanceRouteTest`, `ResourceShowRoutesTest`,
    `CutiNoSuratUpdateTest`, `CutiPdfFilenameTest`, and `IzinPdfEndpointTest`.
- **Decisions Made:**
  - User explicitly selected all local work for publication, including
    prior PKP/Izin/CI/Docker/governance changes, not only this session's fixes.
  - Commit `e0176e7` on main contains 241 changed files, titled
    `feat(pkp): deliver monthly assessments and harden HR document workflows`.
  - Push to `https://github.com/kwanter/simpeg.git` succeeded, advancing main
    from `f36c14c` to `e0176e7`, including 15 previously local commits.
    No merge was needed: main was ahead, not diverged; no force push occurred.
  - New CSS `public/build/assets/app-DR2UAoqb.css` was committed with its
    manifest. `storage/framework/testing-pkp.sqlite` was excluded as runtime
    data. A limited credential-pattern scan found no matches; it was not a
    comprehensive secret audit. Intentional Markdown hard breaks were kept.
- **Next Action / Pending:**
  - Preview containers were left running; runtime liveness is session-local.
    Use `docker compose -f docker-compose.dev.yml down` to stop without
    deleting persisted `docker/mysql-data`.
  - Demo login identities include `user@simpeg.local`, `admin@simpeg.local`,
    `superadmin@simpeg.local`, `atasanpimpinan@simpeg.local`,
    `pimpinan@simpeg.local`, and `verifikator@simpeg.local`; obtain the local
    seed password from the existing environment, not from this memory.
  - Demo records include one approved Izin, one submitted Izin, one approved
    Cuti, and one PKP draft. Both approved letters retain slashed numbers.
  - Hosted CI results and deployment were not verified. Earlier PKP review
    authorization and dependency findings remain pending; do not infer that
    a successful push resolves those risks.
  - This memory-only checkpoint is a new local edit after the published
    commit; it has not been committed or pushed by the save request.

<!-- checkpoint-tail: All four HR defects were fixed and verified with 602
passing tests; all approved local work was pushed as e0176e7; preview was
left running, while hosted CI, dependency disposition, and PKP review remain
unverified or pending. -->

---
