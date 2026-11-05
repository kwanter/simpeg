# Monthly PKP Code and Test Review

**Date:** 2026-10-06  
**Spec:** `spec/spec-feature-monthly-pkp.md` v0.4  
**Plan:** `plan/plan-monthly-pkp.md` v1.2  
**Verdict:** Remediation required; code-phase completion is not approved.

## 1. Executive Summary

Two independent, read-only reviewers evaluated standards/security/test
efficacy and specification compliance. The coordinator reconciled duplicate
findings and independently probed authorization, input normalization, and
draft validation. Production code and existing specifications/plans were
not edited during this review.

There are **14 required findings**, **3 optional recommendations**, and
**1 informational dependency note**. No critical exploit or data-loss
finding was demonstrated. Passing existing tests do not establish completion
of the missing contracts below.

- **Axis A:** Direct policy checks, rational arithmetic, private storage,
  parameterized queries, and aggregate locking are sound foundations.
  Legitimate evidence access, parser bounds, failure responses, runtime
  portability, and verification completeness need remediation.
- **Axis B:** Draft assessor/notes editing, correction entry, simultaneous
  official/pending detail, historical access, queue pagination, archived
  printing, and exact PDF detail do not yet satisfy the specification.

**Delivery readiness: 58/100.** Completeness **22/40**, Clarity **24/30**,
Alignment **12/30**. The score measures this implementation's readiness,
not a replacement score for the approved specification. Missing public
flows reduce completeness; explicit source contracts support clarity;
multiple observed deviations reduce alignment.

## 2. Review Baseline and Scope

The fixed point is HEAD `e6e683cce4afbfb16eebd68c64a5bc473f249e9a`.
There is no new feature commit. A committed three-dot comparison would
omit the implementation, so the input is the scoped working-tree diff
against HEAD plus new, untracked PKP files compared with `/dev/null`.

The pinned input contains 108 files. Its diff SHA-256 is
`d863258c7b4624a64d7ec423f7a48f18535b5eaa41a79bab53045056b758abeb`.
Per-file hashes are recorded in
`docs/review/monthly-pkp-review-input-2026-10-06.json`.
The captured diff is `/tmp/simpeg-pkp-review-2026-10-06.diff`.

Scope includes PKP application code, migrations, views, tests, runtime/CI
configuration, relevant shared-file hunks, and upstream documents.
Unrelated Izin changes, unrelated lockfile churn, bootstrap skills,
generated assets, and test database contents are excluded.

References: `CONSTRAINTS.md` and the review skill's
`CLEAN-CODE-ARCHITECTURE.md`, `FIVE-AXIS-REVIEW.md`,
`SECURITY-HARDENING.md`, and `CODE-SMELLS.md`.

## 3. Axis A: Standards, Security, and Test Efficacy

### [REQUIRED] AUTH-01: Assigned assessor cannot read source evidence

- **Location:** `app/Services/PkpDocumentService.php:177` and
  `app/Policies/PkpPolicy.php:93`.
- **Trigger:** An assigned ordinary assessor downloads a submitted external
  revision's source PDF before deciding its content.
- **Evidence:** Download constructs `PkpContext` without a submission;
  submitted-assessor view authorization requires the current submission's
  UUID and state. The coordinator's model-only probe produced
  `download_without_submission=denied` and
  `download_with_submission=allowed`.
- **Impact:** The authorized assessor receives 403 and cannot examine the
  evidence that approval is intended to cover.
- **Contract:** Spec Sections 4.1/4.2, AC-006/024.
- **Remedy/test:** Resolve the applicable bound attempt and pass it into the
  download context. Test current assessor HTTP access, revoked/unassigned
  denial, nested UUID mismatch, and unchanged source bytes.

### [REQUIRED] PDF-01: Parser resource bounds and error category differ

- **Location:** `app/Services/PkpPdfValidator.php:88` and
  `app/Http/Controllers/PkpController.php:640`.
- **Evidence:** Each process gets 20 seconds, there is no shared 15-second
  budget or 64-KiB output cap, and timeout becomes unavailable/503.
- **Impact:** Parsing can hold application workers and aggregate locks
  beyond the agreed bounds; unlimited output may consume memory.
- **Contract:** Spec Section 4.6 and AC-066/067 require 5 seconds per
  operation, 15 seconds total, 64 KiB per operation, and bound exhaustion
  as 422 `pdf_validation_limit`. Start/unavailable failures remain 503.
- **Remedy/test:** Enforce each budget and a distinct limit failure. Use
  controlled executables for timeout, stdout/stderr flooding, and cumulative
  budget tests; assert HTTP category, rollback, and prior file preservation.

### [REQUIRED] ERR-01: Database failures escape as default 500

- **Location:** `app/Services/PkpDocumentService.php:148` and
  `app/Http/Controllers/PkpController.php:636`.
- **Trigger:** Metadata insertion/commit or serialization fails after a new
  private file is staged.
- **Evidence:** The service compensates then rethrows `QueryException`;
  the HTTP boundary does not normalize it. `PkpDocumentFailureTest` checks
  service rollback, not the required HTTP status/body.
- **Contract:** Spec Sections 4.1/4.5 require safe 503 database/storage
  failure responses without partial effects.
- **Remedy/test:** Classify known infrastructure failures at the PKP boundary
  and prove 503 plus DB/file invariants through HTTP. Retry is optional:
  the spec says a whole transaction **may** retry at most twice. Absence
  of retry alone is not a finding.

### [REQUIRED] ERR-02: Validation responses are inconsistent across adapters

- **Location:** `app/Http/Requests/SavePkpRequest.php:8` and
  `app/Http/Controllers/PkpController.php:593`.
- **Evidence:** FormRequests inherit Laravel's default JSON validation
  envelope; no PKP override supplies `error.code/message/fields`. Several
  service-validation catches return JSON even for HTML forms, including
  final-document decisions, upload, and identity refresh.
- **Impact:** API consumers receive different schemas for the same failure
  family. A browser form can be replaced by a JSON error page instead of
  field feedback with preserved input.
- **Contract:** Spec Section 4.1 requires the named JSON error envelope and
  HTML validation redirects with field errors and non-file input retained.
- **Remedy/test:** Introduce narrow PKP response normalization without
  changing global Cuti/Izin behavior. Test request-level and service-level
  validation through JSON and HTML, including false inspector confirmation.

### [REQUIRED] TEST-01: Real-validator test hard-codes qpdf major 12

- **Location:** `tests/Unit/PkpPdfValidatorTest.php:39`.
- **Evidence:** The real inspection test requires `qpdf version 12` although
  implementation, spec, and readiness jobs permit any supported major >=11.
- **Impact:** A valid qpdf 11 or later non-12 installation fails the suite
  for a local-version assumption rather than application behavior.
- **Contract:** AC-088 and the supported-runtime readiness contract.
- **Remedy/test:** Compare metadata with the actual configured executable's
  version output and assert the supported floor. Keep real parser execution;
  add version-contract fixtures for 11, 12, a later major, and unsupported 10.

### [REQUIRED] TEST-02: Completion evidence omits required scenarios

- **Location:** `docs/pkp-verification.md:53`,
  `tests/Integration/Pkp/PkpWorkflowRaceTest.php:59`,
  `tests/Feature/PkpPdfTest.php:23`, and `e2e/pkp.spec.js:19`.
- **Evidence:** The report claims all 90 AC owners are covered. The eight
  SQL cases have no concurrent identity-history update/approval case. The
  seven browser cases omit correction and identity refresh. PDF assertions
  check magic bytes/length rather than extracted content or multipage layout.
  The browser inventory has no 500-activity round-trip case.
- **Impact:** Counts and checked task flags overstate completed contracts.
  `PkpIdentityService::resolve()` also uses separate latest-date/history/job
  queries; a consistent deployment-database read must be demonstrated,
  not inferred from a PKP parent lock.
- **Contract:** Spec Sections 4.5/6.2, AC-057/075/090, and test traceability.
- **Remedy/test:** Add deterministic PostgreSQL/MySQL identity-update races,
  actual correction/refresh/maximum-tree journeys, and rendered PDF
  content/layout evidence. Map each AC to realized test names and result
  artifacts. Correct blanket completion claims. This review does not assert
  that historical RED runs never occurred; their ticket-level proof is not
  established by current checkboxes.

## 4. Axis B: Functional Specification Compliance

### [REQUIRED] SPEC-01: Archived approved application PDFs are rejected

- **Location:** `app/Services/PkpPdfService.php:30` and
  `app/Policies/PkpPolicy.php:106`.
- **Trigger:** R2 becomes official and the authorized owner prints approved
  R1. Both checks require the current official binding, so R1 returns 409.
- **Contract:** AC-076 explicitly keeps approved archived application
  revisions printable from their snapshots.
- **Remedy/test:** Separate approved historical read/print authorization from
  mutable-current capability. Test R1 after the official switch, immutable
  identity/content, draft denial, and unauthorized callers.

### [REQUIRED] SPEC-02: Draft notes and assessor edits are rejected

- **Location:** `app/Http/Requests/SavePkpRequest.php:52`,
  `app/Services/PkpSubmissionService.php:361`, and
  `resources/views/pkp/edit.blade.php:205`.
- **Evidence:** Valid PUT fields `notes` and `assessor_pegawai_uuid` are
  unknown keys; service save only replaces activities. The editor has neither
  control. The coordinator's validator probe rejected both named fields.
- **Impact:** Notes cannot be authored; a draft created without an assessor
  cannot later select an eligible configured assessor through the edit flow.
- **Contract:** REQ-004/016, Sections 4.2/4.3, AC-028/031/084.
- **Remedy/test:** Add bounded nullable notes and owner assessor options,
  revalidating eligibility/allowed membership under the prescribed lock
  order. Test unset settings later configured, alternatives/revocation,
  notes preservation, snapshots, and HTML/JSON editing.

### [REQUIRED] SPEC-03: Correction entry and capability metadata are missing

- **Location:** `resources/views/pkp/edit.blade.php:20` and
  `app/Http/Controllers/PkpController.php:558`.
- **Evidence:** The revision-create endpoint exists but approved Blade
  detail has no correction form/link. JSON capabilities omit correction
  and identity refresh although the latter exists in the HTML branch.
- **Impact:** Ordinary users cannot start the specified correction through
  the application; JSON clients lack the complete authoritative action set.
- **Contract:** Section 4.2 detail capabilities and AC-044/084.
- **Remedy/test:** Expose policy-derived correction/refresh capabilities and
  a version/base/source correction form. Exercise correction creation through
  approval and official switch with real browser actors.

### [REQUIRED] SPEC-04: Pending correction hides the official read context

- **Location:** `app/Http/Controllers/PkpController.php:449` and
  `resources/views/pkp/assessments.blade.php:18`.
- **Trigger:** R1 is official with pending final inspection; owner creates
  R2. `show()` always selects R2. Its context controls all actions and data.
- **Impact:** Official printing/document actions disappear from owner detail.
  A distinct R1 inspector following its valid document queue link can get
  403 because the detail route evaluates R2 authority instead.
- **Contract:** Sections 4.2/4.5 require official content/completeness to
  remain available alongside pending correction; AC-044/063.
- **Remedy/test:** Resolve official and pending contexts separately, with
  per-context authority. Make queue links open their authorized revision or
  candidate context. Test R1 inspector while R2 is draft and submitted.

### [REQUIRED] SPEC-05: History and frozen first-submission data are absent

- **Location:** `app/Http/Controllers/PkpController.php:542` and
  `app/Policies/PkpPolicy.php:93`.
- **Evidence:** Detail does not expose prior attempts/decisions/assignment
  events/reviews, first-submission time/deadline, or lateness. Returned
  assessor decision history also lacks an authorized read context.
- **Impact:** Retained database rows are not the required public history.
  Users cannot inspect frozen classification or older attempt evidence.
- **Contract:** Section 4.1 assessor decision-history access, Section 4.2
  detail, REQ-010/012, and AC-047/051.
- **Remedy/test:** Add scoped immutable history reads and UI with frozen
  metadata. Test owner/admin access and assessor-only decision history
  without granting foreign or unrelated pending-draft access.

### [REQUIRED] SPEC-06: Both assessment queues are unpaginated

- **Location:** `app/Services/PkpAssessmentService.php:84` and
  `app/Services/PkpAssessmentService.php:127`.
- **Evidence:** Queries end in `get()->all()`; controller ignores page and
  the view has no paginator links.
- **Contract:** Section 4.2 `/pkp/assessments` uses the same 25-row
  pagination as the monthly list. This is required, not optional tuning.
- **Remedy/test:** Use a real paginator with validated page, stable ordering,
  JSON metadata and HTML links. Test 26+ assigned items, page 2, invalid page,
  and authority changes between reads.

### [REQUIRED] SPEC-07: Exact PDF appendix and notes are incomplete

- **Location:** `resources/views/pkp/pdf.blade.php:65` and
  `resources/views/pkp/partials/scores.blade.php:20`.
- **Evidence:** The PDF renders the overall fraction at line 78 but only
  decimal previews for activities/indicators. It has no calculation appendix
  or notes. Screen detail omits exact indicator fractions.
- **Example:** Activities 100 and 200/3 yield indicator 250/3. A second
  indicator can make overall differ, so its fraction cannot substitute for
  the first indicator or activity fraction.
- **Contract:** Sections 3.2/4.6, REQ-004, and AC-038/074/075.
- **Remedy/test:** Render notes and exact activity/indicator details from
  approved snapshots. Assert extracted PDF text using a multi-indicator
  independent oracle and visually verify readable multipage output.

### [REQUIRED] SPEC-08: Raw decimal whitespace is silently normalized

- **Location:** `app/Http/Middleware/TrimStrings.php:17` and
  `app/Http/Requests/SavePkpRequest.php:20`.
- **Evidence:** Global middleware turns JSON decimal `" 1.0000 "` into
  `"1.0000"` before regex validation. The coordinator reproduced this
  transformation without writing to the database.
- **Contract:** Section 4.3 explicitly rejects leading/trailing numeric
  whitespace in invariant decimal strings.
- **Remedy/test:** Preserve/validate PKP raw numeric fields before trimming,
  scoped to this module. Test actual middleware HTTP requests and unchanged
  state; preserve unrelated module normalization.

## 5. Optional Recommendations and Dependency Note

- **[OPTIONAL] PERF-01:** `PkpController.php:854` performs one activity query
  per indicator. Batch the bounded tree read if query count is material.
  Do not introduce a repository framework merely to remove this loop.
- **[OPTIONAL] ARCH-01:** `PkpPdfValidator.php:103` defines named public
  exceptions as sibling classes. Separate PSR-4 files would make isolated
  autoloading reliable. Current controller resolution loads the validator
  first, so a production class-not-found failure was not demonstrated.
- **[OPTIONAL] DELIVERY-01:** Split reviewable commits by complete vertical
  capability and isolate baseline migration portability. Preserve the
  unrelated dirty Izin work. This recommendation grants no staging,
  commit, push, merge, or production-deployment authorization.
- **[FYI] DEP-01:** Earlier audit runs reported 5 Composer advisories and
  9 npm findings. The new direct `brick/math` requirement uses the version
  already present in HEAD's lockfile. No new resolved identity/version was
  demonstrated from that declaration. Existing advisories still need a
  separate reachability/upgrade decision before release; this review did
  not rerun external advisory queries or declare them harmless.

## 6. Threat Model and Verification Assessment

Assets are identity snapshots, performance evidence, private signed PDFs,
assignment authority, audit records, and application availability. Boundaries
are authenticated HTTP/CSRF, actor-to-record/attempt authority, uploaded
bytes-to-qpdf, DB/filesystem commit, and history-to-approved snapshot.

- **Spoofing/elevation:** Direct role/permission/ownership/assignment checks
  avoid generic super-admin Gate bypass; AUTH-01 is an over-denial defect.
- **Tampering:** Rational snapshots, aggregate versioning, and accepted-A
  retention are present. Historical public reads need SPEC-01/04/05.
- **Repudiation:** Events/decisions are stored, but missing history access
  prevents the specified inspection of those records.
- **Disclosure:** Private generated paths, escaped Blade output, parameterized
  queries, and private/no-store/nosniff headers were observed. Error
  normalization must remain generic and omit parser/document output.
- **Denial of service:** PDF-01 and SPEC-06 require explicit bounded work.

Prior execution recorded 471 PHP tests, 7 PKP browser cases, 2 guest browser
cases, 8 cases per SQL engine, and a real Nginx upload-envelope check.
These runs were not repeated for this read-only review. The coordinator's
three isolated probes reproduced AUTH-01, SPEC-02, and SPEC-08.
No skipped tests or suppression-based Floor-Guard bypass was found in the
scoped source. Test animation disabling does not replace business assertions;
it also does not establish normal-motion visual QA.

The reported migration rollback and final Docker cleanup confirmations remain
open. There is no new live SQL identity-race or PDF-layout evidence in this
review. No merge, release, or code-phase completion approval is issued.

## 7. Remediation Handoff

The executable proposal is
`plan/plan-refactor-monthly-pkp-v1.0.md`. It prioritizes public regression
tests and surgical fixes, then restores evidence for all required ACs.
Optional refactoring is separate from required behavior repairs.
After the user approves that plan, invoke `/tdd-write-code` to execute it.
