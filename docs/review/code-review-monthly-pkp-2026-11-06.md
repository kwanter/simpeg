# Monthly PKP Code and Test Review: Re-review

**Date:** 2026-11-06
**Spec:** `spec/spec-feature-monthly-pkp.md` v0.4
**Plan:** `plan/plan-monthly-pkp.md` v1.2 and
`plan/plan-refactor-monthly-pkp-v1.0.md` (TASK-101..TASK-509 complete)
**Fixed point:** HEAD `d309b4c` with implementation commit `e0176e7`
**Supersedes verdict of:** `docs/review/code-review-monthly-pkp-2026-10-06.md`
**Verdict:** All 14 REQUIRED findings are resolved. No blocking defect was
demonstrated. This review does not authorize deployment or release.

## 1. Executive Summary

This re-review verifies the 14 REQUIRED findings raised on 2026-10-06
against the current working tree, instead of re-reading the remediation
plan's completion flags. Every finding was traced to code, and the
authoritative behavior was exercised by the focused PKP suite.

- **Axis A (standards, security, test efficacy):** 6 of 6 REQUIRED
  findings resolved.
- **Axis B (specification compliance):** 8 of 8 REQUIRED findings
  resolved.
- **Optional items:** `PERF-01` remains open, `ARCH-01` is partially
  addressed, `DELIVERY-01` was satisfied by commit `e0176e7`, and
  `DEP-01` is unchanged and still needs a separate decision.
- **New REQUIRED or CRITICAL findings:** none.
- **Delivery readiness: 95/100.** Completeness **38/40**, Clarity
  **28/30**, Alignment **29/30**. No critical-flaw veto applies.

The implementation now satisfies the public contracts that were missing:
assessor evidence reads, bounded PDF parsing with a distinct limit
failure, normalized PKP failure envelopes, configurable draft notes and
assessor, correction entry, separate official and pending read contexts,
scoped history with frozen submission metadata, paginated assessment
queues, exact PDF fractions with an appendix, and module-scoped raw
decimal validation.

## 2. Review Baseline and Scope

The fixed point is HEAD `d309b4c`. The reviewed implementation is commit
`e0176e7` (`feat(pkp): deliver monthly assessments and harden HR document
workflows`); the commits after it are CI, Playwright test, and memory
documentation changes.

Scope is the PKP module: `app/Http/Controllers/PkpController.php`,
`app/Services/Pkp*`, `app/Policies/PkpPolicy.php`, `app/Http/Requests/*Pkp*`,
`app/Http/Middleware/PkpErrorResponses.php`,
`app/Http/Middleware/TrimStrings.php`, `resources/views/pkp/*`,
`routes/web.php` PKP routes, migrations, `config/pkp.php`, PKP PHP and
browser tests, and `docs/pkp-verification.md`.

Verification performed during this review:

- Focused suite `php artisan test --filter=Pkp` with the real qpdf binary:
  **232 passed, 3,578 assertions, 74.00 seconds**.
- Hosted CI for `1b0f3f6`: PHP Tests `42206465224`, PKP Database
  Integration `42206465423`, and Playwright Tests `42206465199` all
  passed.
- Repository-wide Pint passed on 277 PHP files.
- No skipped or disabled test and no suppression directive exists inside
  the PKP scope (`tests/`, `e2e/`), so no Floor-Guard bypass was found.

## 3. Axis A: Standards, Security, and Test Efficacy

- **AUTH-01: Resolved.**
  - Evidence: `app/Services/PkpDocumentService.php:174-185` and
    `app/Policies/PkpPolicy.php:98-103`.
  - The download resolves the bound attempt (approved submission, else
    current submission) and passes it into `PkpContext`. The policy
    grants the assigned inspector on an approved attempt and the assigned
    assessor on the current submitted attempt.
- **PDF-01: Resolved.**
  - Evidence: `app/Services/PkpPdfValidator.php:12-16,97-133` and
    `app/Http/Controllers/PkpController.php:696-699`.
  - A 5-second operation budget, a 15-second total deadline, and a
    64 KiB output cap are enforced. Bound exhaustion raises
    `PdfValidationLimit`, mapped to HTTP 422 `pdf_validation_limit`;
    start or version failure stays 503 `pdf_validator_unavailable`.
- **ERR-01: Resolved.**
  - Evidence: `app/Http/Middleware/PkpErrorResponses.php:26-29`.
  - `QueryException` becomes 503 `database_unavailable` and
    `FilesystemException` becomes 503 `document_storage_failed` inside
    the PKP route group, without changing other modules.
- **ERR-02: Resolved.**
  - Evidence: `app/Http/Middleware/PkpErrorResponses.php:24-25,48-62`
    and `PkpController.php:1144`.
  - JSON failures carry `error.code`, `error.message`, and
    `error.fields`, plus a compatibility `errors` map. Non-JSON non-GET
    failures return `back()->withErrors()` and `withInput()` excluding
    files, so a browser keeps field feedback and input.
- **TEST-01: Resolved.**
  - Evidence: `tests/Unit/PkpPdfValidatorTest.php:64-85,125-127`.
  - The real-validator test compares against the configured executable's
    version output and asserts a supported floor of 11 or newer, with
    fixtures for 11.9.1, 12.4.0, 14.0.0, and unsupported 10.6.0.
- **TEST-02: Resolved.**
  - Evidence: `tests/Integration/Pkp/PkpWorkflowRaceTest.php:84-132`,
    `tests/Feature/PkpPdfTest.php:57-59,113-121,139-167`, and
    `e2e/pkp.spec.js:319,394,470,546,584`.
  - Committed-worker identity and approval races exist for both SQL
    engines, browser journeys cover correction entry, identity refresh,
    and the 500-activity round trip, and PDF assertions read extracted
    text, multipage layout, exact `200/3` fractions, and non-clipped
    baselines rather than magic bytes.

## 4. Axis B: Functional Specification Compliance

- **SPEC-01: Resolved.**
  - Evidence: `app/Services/PkpPdfService.php:30-46` and
    `app/Policies/PkpPolicy.php:111-117`.
  - Printing authorizes the requested revision by its own approved
    attempt, independent of the current official binding, so archived R1
    stays printable after R2 becomes official. Draft revisions still fail
    with 409 `revision_not_approved`.
- **SPEC-02: Resolved.**
  - Evidence: `app/Http/Requests/SavePkpRequest.php:23-25`,
    `app/Services/PkpSubmissionService.php:366-391`, and
    `resources/views/pkp/edit.blade.php:80-81`.
  - `notes` (max 5,000) and `assessor_pegawai_uuid` are accepted,
    revalidated against the configured allowed set under the existing
    lock order, persisted, and edited through the draft form.
- **SPEC-03: Resolved.**
  - Evidence: `resources/views/pkp/edit.blade.php:55-62`,
    `PkpController.php:614`, and `e2e/pkp.spec.js:361-364`.
  - The approved detail exposes a correction form carrying
    `expected_version`, `base_revision_uuid`, and `source`; JSON
    capabilities include `revise` and `refresh_identity`. The browser
    journey starts a correction and reaches the draft editor.
- **SPEC-04: Resolved.**
  - Evidence: `PkpController.php:472-477,583-597` and
    `resources/views/pkp/assessments.blade.php:21`.
  - Official and pending reads are resolved, authorized, and serialized
    separately, and each carries its own authority. Queue links pass
    `revision_uuid`, so an inspector following the official queue opens
    the official revision rather than a private pending draft.
- **SPEC-05: Resolved.**
  - Evidence: `PkpController.php:585,599-601,929-954` and
    `resources/views/pkp/partials/history.blade.php:23`.
  - Detail returns attempts with frozen input, identity, assessor, and
    score snapshots, plus decisions, document reviews, and actor-scoped
    events. Frozen `first_submitted_at`, `deadline_snapshot_at`, and
    `lateness` are exposed.
- **SPEC-06: Resolved.**
  - Evidence: `app/Services/PkpAssessmentService.php:68-76,109,151` and
    `resources/views/pkp/assessments.blade.php:27`.
  - Both queues use `paginate(25, ..., 'page', $page)` with a validated
    page floor, stable ordering, and rendered pagination links.
- **SPEC-07: Resolved.**
  - Evidence: `resources/views/pkp/pdf.blade.php:88-96` and
    `tests/Feature/PkpPdfTest.php:113-121,148-149`.
  - The PDF renders notes and a calculation appendix with exact activity
    and indicator fractions, and the assertion set proves distinct
    fractions such as `200/3` and `250/3` in extracted text.
- **SPEC-08: Resolved.**
  - Evidence: `app/Http/Middleware/TrimStrings.php:9-16` and
    `app/Http/Requests/SavePkpRequest.php:20,53-57`.
  - Decimal strings are preserved from trimming only for
    `PUT pkp/*/revisions/*`, so leading or trailing numeric whitespace
    now fails the strict decimal regex instead of being normalized.
    Unknown keys remain rejected.

## 5. Optional and Dependency Items

- **[OPTIONAL] PERF-01: open.** `PkpController::revisionActivityTree`
  (`app/Http/Controllers/PkpController.php:1019-1043`) still runs one
  activity query per indicator. Work is bounded by the 50-indicator cap,
  so this remains tuning rather than a defect.
- **[OPTIONAL] ARCH-01: partially addressed.** `PdfValidationLimit` now
  lives in its own file (`app/Services/PdfValidationLimit.php`), but
  `InvalidPdf` and `PdfValidatorUnavailable` are still sibling classes
  inside `app/Services/PkpPdfValidator.php:139-141`. No production
  autoload failure was demonstrated.
- **[OPTIONAL] DELIVERY-01: satisfied for this work.** The PKP capability
  landed as a reviewable commit (`e0176e7`) with CI, browser, and
  documentation evidence attached.
- **[FYI] DEP-01: unchanged.** Composer advisories and npm findings still
  need a separate reachability and upgrade decision. This review did not
  rerun advisory queries and does not declare them harmless.

## 6. New Findings

No CRITICAL, REQUIRED, or new functional finding was demonstrated.

Two observations are recorded for future tuning only:

- **PERF-01 restated:** the per-indicator activity query is acceptable
  under the 50-indicator bound but is the first candidate if PDF and queue
  latency ever regresses.
- **ARCH-01 restated:** two remaining sibling exception classes make
  isolated autoloading of `PkpPdfValidator` depend on class-map order.

## 7. Threat Model and Verification Assessment

Assets are identity snapshots, performance evidence, private signed PDFs,
assignment authority, audit records, and application availability.

- **Spoofing and elevation:** policy checks remain direct role,
  permission, ownership, and assignment tests rather than a generic
  super-admin bypass. Correction, reassignment, and document decisions are
  authorized per revision and per attempt.
- **Tampering:** rational snapshots, aggregate versioning,
  `expected_version` concurrency checks, and accepted-A retention are
  present. Historical reads are scoped, and pending drafts are not exposed
  to the official inspector.
- **Repudiation:** events, decisions, document reviews, and frozen
  submission metadata are now readable through authorized history.
- **Disclosure:** private storage paths, escaped Blade output,
  parameterized queries, and private/no-store/nosniff document headers
  were observed. Failure envelopes stay generic and omit parser output.
- **Denial of service:** parser work is bounded per operation and in
  aggregate, and both assessment queues are paginated.

Residual risks outside this review's authority are deployment-time: hosted
CI is green, but no staging migration, production release, or real-device
visual QA was performed.

## 8. Readiness Scoring

- **Completeness: 38/40.** All 14 REQUIRED findings closed; 102 realized
  test names map the 90 acceptance criteria; only bounded tuning items
  remain.
- **Clarity: 28/30.** Contracts are explicit in code, error envelopes are
  named, and test seams are pre-agreed; two optional items are still
  described rather than implemented.
- **Alignment: 29/30.** Implementation traces to spec Sections 4.1, 4.2,
  4.5, and 4.6 and the cited acceptance criteria, with one optional
  deviation in query design.
- **Total: 95/100.** No critical-flaw veto applied.

## 9. Handoff

Required remediation is complete, so no new refactoring plan is produced
and `plan/plan-refactor-monthly-pkp-v1.0.md` is not rewritten.

Recommended order for what remains:

1. `TASK-50A` is approved on 2026-11-06 in
   `plan/plan-refactor-monthly-pkp-v1.0.md`, closing the completion and
   re-review checkpoint recorded in this report.
2. Decide `DEP-01` (Composer advisories and npm findings) before any
   release decision; green CI does not resolve dependency risk.
3. Schedule `PERF-01` and `ARCH-01` as optional hardening, with a
   regression test each, only if they are pulled forward.

Deployment, staging migration, and release remain separately authorized.
