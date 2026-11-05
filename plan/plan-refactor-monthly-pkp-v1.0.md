---
goal: Remediate monthly PKP public contracts and verification gaps
version: 1.0
date_created: 2026-10-06
last_updated: 2026-10-07
status: In Progress
execution_approved: true
active_phase: 5
tags: [refactor, clean-code, architecture, security, tdd]
---

# Refactoring & Remediation Plan: Monthly PKP

## 1. Traceability: Requirements & Constraints

Source review: `docs/review/code-review-monthly-pkp-2026-10-06.md`.
Source baseline: HEAD `e6e683cce4afbfb16eebd68c64a5bc473f249e9a`
plus the scoped working tree pinned in
`docs/review/monthly-pkp-review-input-2026-10-06.json`.
Upstream: monthly PKP spec v0.4 and implementation plan v1.2.

The user approved this remediation plan on 2026-10-06 and authorized Phase 1.
Later phases retain their explicit approval checkpoints.
File IDs below resolve to exact paths in Section 4. Task tables use these
IDs to keep the template readable; each packet's file scope is explicit.
Existing public seams and immutable evidence remain authoritative.

- **SEC-001:** AUTH-01; restore assigned source access while preserving
  revocation, cross-record denial, and owner/admin history boundaries.
- **SEC-002:** PDF-01; enforce 5-second operation, 15-second total, and
  64-KiB output bounds with the correct 422/503 distinction.
- **REQ-001:** ERR-01/02; stable module-local JSON/HTML failure contracts,
  safe infrastructure 503, and preserved DB/filesystem state.
- **TEST-001:** TEST-01; real qpdf verification supports all majors >=11.
- **REQ-002:** SPEC-02/08; editable notes/assessor and strict raw decimal
  inputs under real middleware, without global behavior changes.
- **REQ-003:** SPEC-03; policy-derived correction/refresh actions through
  Blade and JSON with the existing version/base/source contract.
- **REQ-004:** SPEC-04/05; official and pending contexts plus immutable
  authorized history and frozen first-submission classification.
- **REQ-005:** SPEC-06; both queues paginate 25 rows with stable ordering.
- **REQ-006:** SPEC-01/07; archived approved printing, notes, exact
  activity/indicator details, and readable multipage PDF output.
- **TEST-002:** TEST-02; deployment-engine identity races, representative
  browser/max-tree flows, and realized coverage/evidence for all 90 ACs.
- **PRN-001:** Surgical vertical packets; reuse direct authorization,
  rational calculator, snapshots, and existing storage/events. No generic
  repository framework, global Gate rewrite, or unrelated Izin changes.
- **PRN-002:** Existing `CONSTRAINTS.md` floors remain unchanged. No skips,
  suppressions, weakened assertions, or blanket completion claims.

## 2. Implementation Steps

> [!IMPORTANT]
> **EXECUTION DIRECTIVE FOR `/tdd-write-code`:** Execute each phase through
> strict TDD. Observe a failing public regression (RED), implement the
> smallest correction (GREEN), and run the required gates (VERIFY).
> After each phase, **STOP AND WAIT** for explicit user approval before the
> next phase. Approval of this document alone does not authorize production
> deployment, staging, commits, push, or merge. Follow the active Git policy.

Do not edit upstream product requirements to match incomplete code. If a
necessary change introduces new product behavior or expands the exact file
scope below, pause that packet and route it to the appropriate spec/plan
revision. Already-green behavior is characterization, not invented RED.

### Implementation Phase 1: Evidence Access and Safe Failure Boundaries

- **GOAL-001:** An assigned assessor can examine external evidence, while
  invalid/unbounded uploads and infrastructure failures return safe,
  contract-compliant outcomes with preserved prior evidence.
- **Packet 1A scope:** F02, F03, F17; SEC-001.
- **Packet 1B scope:** F01, F04, F18, F17; SEC-002/TEST-001. A distinct
  parser-limit exception may be added as F34 only when the HTTP RED needs it.
- **Packet 1C scope:** F01, F19, F20, F37, F38; REQ-001. F37 is a narrow
  PKP route error adapter, not a replacement global exception handler.

| Task ID | Description (files and TDD steps) | Ref ID | Completed | Date |
| --- | --- | --- | :---: | :---: |
| TASK-101 | RED: F17 assigned source + revoked denial | SEC-001 | [x] | |
| TASK-102 | GREEN: F02 attempt context; retain F03 rules | SEC-001 | [x] | |
| TASK-103 | RED: F18/F17 parser budget + HTTP compensation | SEC-002 | [x] | |
| TASK-104 | GREEN: F04 budgets; F01 distinct limit response | SEC-002 | [x] | |
| TASK-105 | RED: F18 versions 11/12/later and invalid 10 | TEST-001 | [x] | |
| TASK-106 | GREEN: F18 compare real executable version | TEST-001 | [x] | |
| TASK-107 | RED: F19/F20 DB503 and JSON/HTML field errors | REQ-001 | [x] | |
| TASK-108 | GREEN: F01/F37/F38 normalize PKP failures | REQ-001 | [x] | |
| TASK-109 | VERIFY: focused, full PHP, Pint, real qpdf | PRN-002 | [x] | |
| TASK-10A | APPROVAL: wait before Phase 2 | PRN-001 | [x] | |

The source-access RED must use HTTP/session/provider boundaries, not only a
policy mock. Current assessor may inspect its current source; former and
unassigned actors remain denied. Scope a historical attempt independently.

Parser tests must distinguish clean/warning/corrupt/encrypted exit codes,
stdout and stderr cap overflow, per-operation timeout, total deadline, and
failure to start. Kill limit-exceeding processes promptly and retain generic
user errors. An actual valid PDF must still be inspected by real qpdf.

Infrastructure tests must force known metadata/storage failures through
HTTP and assert aggregate version, current bindings, review rows, and files.
Do not catch all programmer errors as successful recovery. Full-transaction
retry is optional and limited to original plus at most two transient retries;
each retry must revalidate authority/version and never blindly repeat a
committed transition. Preserve compensation only for unreferenced staging.

JSON validation must expose `error.code/message/fields`; HTML validation
must redirect with field errors and retain non-file input. Include request
validation and service rejection, particularly final-match confirmation.

#### Phase 1 Execution Evidence: 2026-10-06

TASK-101..109 completed; the user approved Phase 2 on 2026-10-06. AUTH-01,
PDF-01,
ERR-01, ERR-02, and TEST-01 have implementation and regression evidence.
The other required review findings remain open in later phases.

Observed RED results before their corresponding corrections:

- Assigned source download returned 403 instead of 200.
- Five parser unit cases did not throw the required limit failure; three
  HTTP budget cases incorrectly created a candidate with 201 instead of 422.
- A wrapper reporting supported qpdf 11 and delegating actual parsing to
  the installed executable failed the hard-coded major-12 assertion.
- Metadata and filesystem-write failures returned 500 instead of 503.
- JSON validation/read failures omitted required module error codes.
- HTML invalid-PDF and inspector-confirmation failures returned 422 JSON
  instead of redirecting with field feedback and preserved non-file input.

GREEN resolves the bound submission context, parser budgets/error type, real
executable version assertions, and PKP-only failure response middleware.
The existing HTML request-validation redirect was a passing characterization,
not manufactured RED. Filesystem mocking injects only a write failure; HTTP,
authorization, metadata, accepted evidence, and download assertions remain
real. No unrelated Izin behavior or dependency versions were changed.

Verification results:

| Gate | Result |
| --- | --- |
| Full Unit/Feature suite | 493 passed; 1,928 assertions; 74.17 seconds |
| PHP formatting | 274 files pass |
| Production frontend build | Pass |
| Dedicated PKP Chromium | 7 passed; 39.4 seconds |
| Base Chromium regression | 2 passed; 4.3 seconds |
| Real configured qpdf | 12.4.2; real PDF inspection passes |
| Version portability | 11/12/later fixtures; wrapped real parser 11 passes |
| New PHP syntax | Pass |
| Markdown lint | Plan and architecture pass |
| Scoped whitespace check | Pass |

The browser runs used the already available Chrome for Testing executable
from cache revision 1228 through `PKP_CHROMIUM_EXECUTABLE`. This project has
no TypeScript configuration or configured PHP static-analysis runner;
`tsc --noEmit` is not claimed as passed. Native PHP syntax/runtime checks,
Pint, and the frontend build were performed. Dependency audits, real SQL
identity races, deployment stack recovery, and full 90-AC reconciliation
remain Phase 5 work. Prior advisory findings remain open.

Representative executed command families:

```sh
PKP_QPDF_BINARY=/opt/homebrew/bin/qpdf php artisan test
./vendor/bin/pint --test
npm run build
PKP_QPDF_BINARY=/path/to/qpdf \
  PKP_CHROMIUM_EXECUTABLE=/path/to/chromium \
  npx playwright test --config=playwright.pkp.config.js \
  --project=chromium --reporter=line
PKP_CHROMIUM_EXECUTABLE=/path/to/chromium \
  npx playwright test --project=chromium --reporter=line
```

No commit, deployment, production migration, or memory write occurred.

### Implementation Phase 2: Complete Draft and Correction Authoring

- **GOAL-002:** Owners can finish an unset-assessor draft, enter notes,
  use strict decimal inputs, and initiate corrections through the module.
- **Packet 2A scope:** F06, F09, F12, F20, F21; REQ-002.
- **Packet 2B scope:** F09, F10, F21; raw numeric normalization. F10 may
  change only if an explicitly PKP-scoped solution requires it.
- **Packet 2C scope:** F01, F12, F22, F27; REQ-003.

| Task ID | Description (files and TDD steps) | Ref ID | Completed | Date |
| --- | --- | --- | :---: | :---: |
| TASK-201 | RED: F20/F21 notes and allowed assessor edits | REQ-002 | [x] | |
| TASK-202 | GREEN: F06/F09/F12 bounded draft contract | REQ-002 | [x] | |
| TASK-203 | RED: F21 raw whitespace HTTP422, no mutation | REQ-002 | [x] | |
| TASK-204 | GREEN: F09/F10 module-scoped raw validation | REQ-002 | [x] | |
| TASK-205 | RED: F22/F27 correction and refresh capability | REQ-003 | [x] | |
| TASK-206 | GREEN: F01/F12 existing correction form/action | REQ-003 | [x] | |
| TASK-207 | VERIFY: PHP/Pint/build + correction Chromium | PRN-002 | [x] | |
| TASK-20A | APPROVAL: wait before Phase 3 | PRN-001 | [x] | |

Reuse the existing notes column and snapshot contract. Enforce 5,000-character
notes and nullable behavior; allowlist/eligibility validation must happen
again inside the locked mutation. Test newly configured owner settings,
eligible alternative, revoked option, forbidden self-selection, stale
version, and returned-draft edits for both sources.

Preserve raw numeric whitespace rejection without changing unrelated
middleware semantics. Test actual HTTP middleware for each decimal field,
scale/range boundaries, and untouched aggregate/activity rows on rejection.

The correction form carries expected version, current official base, and
the approved source choices. Duplicate/pending conflict and unauthorized
actors must still fail. JSON action booleans include correction and identity
refresh and derive from the same authoritative boundary as Blade.

#### Phase 2 Execution Evidence: 2026-10-06

TASK-201..207 completed; the user approved Phase 3 on 2026-10-07. SPEC-02,
SPEC-03,
and SPEC-08 have implementation and regression evidence. Phase 3..5 findings
remain open; no blanket 90-AC completion claim is made.

Observed RED results before their corresponding corrections:

- Valid draft notes/assessor requests returned 422 and the editor omitted
  the required controls.
- Sixteen whitespace variants across four numeric fields returned 200;
  associative indexes also bypassed the initial targeted preservation rule.
- Detail JSON omitted correction/refresh booleans. The browser reached
  approved content but could not find the correction button.
- The new correction browser flow then reached R2 editing, but untouched
  database-loaded numbers were sent as JSON numbers and rejected as strings.
- A valid `0.000001` credit appeared as a numeric exponent in the edit
  payload and failed submission. Decimal normalization first retained an
  extra trailing zero; the unchanged regression still rejected that excess
  scale until canonical zero stripping was applied.

GREEN adds bounded notes and allowed assessor editing on both draft sources,
locked assignment revalidation, module-scoped raw decimal preservation,
explicit whitespace rejection, and canonical decimal-string projections.
The decimal conversion strips only trailing zeros and expands stored exponent
notation; it does not round or change rational score calculations. The small
credit oracle proves realized credit `1/500000` for quantity 2.

Owner correction/refresh capabilities use the authoritative boundary and
state predicates. The HTML correction form submits current version, base,
and source to the existing endpoint. Returned notes edits preserve the prior
attempt snapshot/hash. Revoked, inactive, unverified, soft-deleted, self, and
foreign-owner cases preserve state on rejection. Existing normalization
outside PKP remains a passing characterization; direct-service metadata
guards have additional state-based characterization tests.

Two fixture setup mistakes were corrected without changing production
contracts: configuration revocation retains at least one valid allowed
alternative, and unverified-user setup uses explicit assignment because
`email_verified_at` is not mass assignable. These setup failures are not
counted as application RED evidence.

| Gate | Result |
| --- | --- |
| Focused draft/activity/revision tests | 54 passed; 399 assertions |
| Full Unit/Feature suite | 524 passed; 2,180 assertions; 74.50 seconds |
| PHP formatting | 274 files pass |
| Production frontend build | Pass |
| Dedicated PKP Chromium | 8 passed; 44.5 seconds |
| New correction + external metadata | 2 focused browser cases pass |
| Base Chromium regression | 2 passed |
| Changed PHP syntax | Pass |
| Markdown lint and scoped whitespace | Pass |

The eight-case browser run includes actual metadata save for both sources,
an alternative assessor, correction creation/edit/approval, official switch,
and R1 printing while R2 is pending. It does not claim archived R1 printing
after R2 approval, dual-context detail, complete history, identity refresh
browser coverage, or a 500-activity round trip; those remain later work.
SQL deployment race/recovery and dependency-audit gates remain Phase 5.
TypeScript/static-analysis availability is unchanged from Phase 1.
No commit, deployment, production migration, or memory write occurred.

### Implementation Phase 3: Official, Pending, History, and Queue Reads

- **GOAL-003:** Owners and authorized reviewers can inspect the correct
  immutable context while another revision is pending, with scoped history
  and bounded outstanding-work lists.
- **Packet 3A scope:** F01, F03, F12, F15, F22; REQ-004.
- **Packet 3B scope:** F01, F03, F12, F23; authorized history. A focused
  history view may be added as F36; do not introduce a new purge route.
- **Packet 3C scope:** F01, F07, F15, F26; REQ-005.

| Task ID | Description (files and TDD steps) | Ref ID | Completed | Date |
| --- | --- | --- | :---: | :---: |
| TASK-301 | RED: F22 R1 inspector while R2 is pending | REQ-004 | [x] | |
| TASK-302 | GREEN: F01/F03/F12 per-revision read contexts | REQ-004 | [x] | |
| TASK-303 | RED: F23 attempts/history and frozen lateness | REQ-004 | [x] | |
| TASK-304 | GREEN: F01/F03/F12 scoped immutable history | REQ-004 | [x] | |
| TASK-305 | RED: F26 both queues with 26+ assigned rows | REQ-005 | [x] | |
| TASK-306 | GREEN: F07/F01/F15 real 25-row pagination | REQ-005 | [x] | |
| TASK-307 | VERIFY: PHP/Pint/build + queue/history browser | PRN-002 | [x] | |
| TASK-30A | APPROVAL: wait before Phase 4 | PRN-001 | [x] | 2026-10-07 |

Detail must expose official-approved content/completeness and pending state
separately. Each context has its own capabilities, source/final/candidate
references, and authorized queue destination. Test distinct R1 inspector/R2
assessor actors; R1 review remains reachable during R2 draft/submission.
Do not permit an assessor's official access to reveal unrelated R2 drafts.

History includes immutable attempts, decisions, assignment/review evidence,
first-submitted instant, deadline snapshot, and lateness. Owners/view-all
actors read full records; assessor history is limited to authorized attempt
and decision history. Test return, refresh, reassignment, correction, and
archived file references. Live draft rows must not substitute for old inputs.

Both queue kinds validate page >=1, return paginator JSON metadata, and
render links with stable descending period/UUID ordering. Test page 2,
invalid page, empty list, revoked authority, and a correction with official
document work. Preserve the monthly-list response contract.

#### Phase 3 Execution Evidence: 2026-10-07

SPEC-04, SPEC-05, and SPEC-06 have implemented public regression evidence.
At Phase 3 completion, TASK-30A awaited approval before Phase 4. The
verification packet includes F27 browser history/queue journeys, as required
by TASK-307. The user subsequently approved Phase 4 on 2026-10-07.

Observed RED results before their corresponding corrections:

- R1's inspector received 403 when R2 was pending. The new read resolver
  authorizes each revision separately and queue links identify their revision.
- A returned assessor's historical attempt received 403; authorized owner
  detail omitted history arrays. Archived approved read mode was incorrectly
  reported as non-historical. Historical views now use frozen snapshots and
  expose no mutation capabilities.
- A 27-item content queue returned all 27 instead of 25. Both queue kinds now
  have real paginator metadata, validated page, links, and assignment scope.
- Equal-period items used ascending UUIDs rather than the prescribed
  descending tie order; invalid direct queue filters succeeded silently.
- The browser could not find a decision-history entry after return. A scoped
  post-decision link now opens the actor's authorized snapshot/history.
- Official/pending completeness fields were absent. They now distinguish
  incomplete, content-waiting, upload-waiting, inspection-waiting, and complete
  without replacing accepted A's completeness while B awaits inspection.

GREEN preserves separate authorized official/pending read data and frozen
first-submit classification. Owners/view-all actors receive their full
record history; other actors receive assigned attempts or their own content
decisions. Historical source downloads require the decision/source relation;
the replacement source and live private notes remain inaccessible to the old
assessor. Private storage paths are excluded from serialized document history.
An explicit attempt selection is read-only even for the owner. Nested UUID
and malformed selector tests preserve denial and membership boundaries.

Queue compatibility retains the existing first-page `queue()` array adapter;
`queuePage()` supplies the paginated HTTP contract. Existing empty-array
assertions were retained. Tests exercise 27 actual submitted records per kind,
foreign assignment exclusion, page 2, empty lists, invalid filters, and
inactive account denial. A missing import in the first document-queue fixture
was corrected; that setup failure is not counted as application RED evidence.

Browser assertions were narrowed to the selected revision region after
approved history UI introduced duplicate note text. This strengthens the
positive context check; the R2 privacy assertion still covers the entire
page. No assertion was removed, skipped, or weakened. The new browser flow
accepts R1's candidate while R2 remains a draft for a different assessor.

| Gate | Result |
| --- | --- |
| Focused read/history/queue/reassignment | 30 passed; 387 assertions |
| Full Unit/Feature suite | 534 passed; 2,353 assertions; 86.73 seconds |
| PHP formatting | 274 files pass |
| Production frontend build | Pass |
| Dedicated PKP Chromium | 9 passed; 1.1 minutes |
| Base Chromium regression | 2 passed |
| Focused history navigation | Pass |
| Focused correction + dual inspection | 2 passed; 23.8 seconds |
| Changed PHP syntax | Pass |
| Markdown lint and whitespace | Pass |

The full PHP gate was repeated after the completeness regression/fix.
Live SQL races, stack/migration recovery, full AC evidence, and audit triage
remain Phase 5. Archived approved PDF printing and the exact calculation
appendix remain Phase 4. Type/static-analysis availability is unchanged.
No commit, deployment, production migration, or memory write occurred.

### Implementation Phase 4: Complete Immutable PDF Evidence

- **GOAL-004:** Authorized archived approved printing remains available,
  and current/archived application PDFs contain notes and exact calculations.
- **Packet 4A scope:** F03, F05, F24, F23; archived print.
- **Packet 4B scope:** F13, F14, F24, F25; PDF/screen exact details.
- **Verification compatibility:** F17, only the generated-fixture page-count
  assertion. The calculation appendix starts on a new page to preserve
  repeated headers, so this approved application fixture is now two pages.
  Keep the real parser metadata, hash, binding, and privacy assertions.
  F27 additionally updates the existing exact-score journey to assert each
  overall, indicator, and activity fraction after the newly required
  indicator detail adds a third instance. No new browser feature scope.

| Task ID | Description (files and TDD steps) | Ref ID | Completed | Date |
| --- | --- | --- | :---: | :---: |
| TASK-401 | RED: F24 R1 print after R2 approval | REQ-006 | [x] | 2026-10-07 |
| TASK-402 | GREEN: F03/F05 archived print | REQ-006 | [x] | 2026-10-07 |
| TASK-403 | RED: F24/F25 notes/exact fractions | REQ-006 | [x] | 2026-10-07 |
| TASK-404 | GREEN: F13/F14 notes + appendix | REQ-006 | [x] | 2026-10-07 |
| TASK-405 | VERIFY: PDF render/PHP/Pint | PRN-002 | [x] | 2026-10-07 |
| TASK-40A | APPROVAL: wait before Phase 5 | PRN-001 | [x] | 2026-10-07 |

Printing authorization must not enable archived mutations. Test draft
409, unauthorized actors, archived approved application success, and exact
external-source bytes. Preserve frozen identity and the approved assessor.

Use an independent multi-indicator oracle: activity 200/3, indicator 250/3,
and a distinct overall result. Assert notes and fraction details in the
actual generated PDF text, not a Blade render alone. Include long descriptions
and multiple pages; verify repeated headers, all text/summaries/signature,
A4 landscape, and readable type. Preserve no-rounding semantics.

#### Phase 4 Execution Evidence: 2026-10-07

The user approved Phase 4 on 2026-10-07. TASK-401..405 completed with
implementation, public regression, and runtime verification evidence.
SPEC-01 and SPEC-07 are addressed within the approved snapshot/print seams.

Observed RED results before the corresponding corrections:

- R1 application print after R2 approval returned 409 instead of 200;
  archived signed-external print also returned 409 instead of unchanged bytes.
- The independent multi-indicator HTTP oracle omitted indicator 250/3 on
  screen; generated PDF text omitted frozen notes and calculation details.
- The first multipage render repeated main table headers but not calculation
  appendix headers; its regression observed one header instead of multiple.
- The activity display 66,666666... split across lines in its narrow column;
  the actual-PDF text regression failed before widening that column.

GREEN removes only the latest-official restriction from approved printing,
retaining scoped approved-submission authorization. Archived reads advertise
print capability without enabling upload or mutation; owner and historical
content decision-maker can print, while a foreign owner receives 403.
Application R1 notes stay frozen after distinct R2 notes become official.
External R1 printing streams the original approved source bytes unchanged.
Draft printing remains 409, and archived mutation remains 404 under the
existing pending-revision boundary.

The PDF uses immutable notes, identity, assessor, and score snapshots. Its
calculation appendix contains activity achievement, realized credit,
indicator score, and overall exact fractions. Independent oracle values are
200/3, 250/3, 100/3, and 175/3; realized credit for quantity 2 and credit 1
is 2/1 under the approved formula. All activities, including terminating
scores, and each indicator expose exact fractions on screen. Arithmetic
and no-rounding behavior are unchanged.

Real PDF text assertions decode Dompdf DejaVu UTF-16BE runs from qpdf QDF
content streams; they do not assert only a Blade render. This uses the
existing qpdf CI prerequisite without adding a runtime dependency. Local
Poppler text extraction independently verifies the generated PDF, and its
rendered pages were visually inspected. The long-content fixture has 18
unique activity start/end markers across 25 nonempty A4 landscape pages
(841.89 by 595.28 points), repeated main/appendix headers, notes, complete
summaries, and an intact signature block. Text stays readable without font
reduction; current profile changes do not leak into the approved identity.

Verification compatibility adds F17's generated-fixture page-count
expectation and F27's score-detail assertions to Packet 4B: the document now
includes a separate calculation appendix page, so inspected metadata is
two pages rather than one. The first full-suite attempt exposed that legitimate
fixture change and a
shared storage-fixture collision caused by overlapping test processes.
The final suite is run sequentially; no assertions are removed or skipped.
The first browser run passed eight journeys and exposed its stale count of
two equal fractions; the indicator detail now adds a third. F27 verifies all
three named categories and their visible exact value independently.
The architecture map records archived printing and the actual-PDF test seam.

Final sequential PHP verification passed 538 tests with 2,457 assertions in
91.57 seconds. Pint passed all 274 files; syntax checks passed for all six
changed PHP files. Frontend production build and Markdown lint passed.
All nine authenticated Chromium PKP journeys passed in 1.1 minutes; both
base guest browser tests passed in 4.9 seconds.
No standalone TypeScript/PHP static-analysis configuration exists; native
PHP checks, runtime tests, Pint, and the existing frontend build are the
available gates. The immutable spec v0.4 hash is unchanged.

Local verification artifacts are `/tmp/pkp-phase4-full-tests.log`,
`/tmp/pkp-phase4-browser-final.log`, `/tmp/pkp-phase4-multipage.pdf`,
and `/tmp/pkp-phase4-multipage.txt`. Reproduce the real PDF evidence with:

```sh
PKP_QPDF_BINARY=/opt/homebrew/bin/qpdf \
PKP_PDF_EVIDENCE_PATH=/tmp/pkp-phase4-multipage.pdf \
php artisan test --filter=PkpPdfTest
pdfinfo /tmp/pkp-phase4-multipage.pdf
pdftotext -layout /tmp/pkp-phase4-multipage.pdf \
  /tmp/pkp-phase4-multipage.txt
pdftoppm -scale-to 1100 -png /tmp/pkp-phase4-multipage.pdf \
  /tmp/pkp-phase4-page
```

TASK-40A remains the explicit approval checkpoint before Phase 5. The
90-acceptance-criterion inventory, deployment identity races, max-tree
browser proof, dependency audit disposition, and runtime recovery evidence
remain Phase 5 work; this phase does not claim their completion.

### Implementation Phase 5: Deployment Races and Honest Completion Evidence

- **GOAL-005:** Prove required scenarios on real public/runtime seams and
  reconcile completion claims with realized results.
- **Packet 5A scope:** F08, F28, F29; TEST-002 identity-read consistency.
- **Packet 5B scope:** F27, F21, F22; browser/max-tree proof.
  The 500-activity RED additionally scopes F12 client-key initialization:
  server-loaded rows have no Alpine keys and render zero editable rows.
  F41 supplies only create/view history permissions to the guarded test admin
  so identity changes use the existing real history UI and middleware.
  F06 preserves first-submit/deadline raw timestamp precision after the
  browser refresh/resubmit RED and the microsecond HTTP regression.
  The deleted-Pegawai HTTP RED also scopes F06 owner lookup with trashed
  rows solely so the existing policy denies them with 403 before mutation.
  F01/F06 restore the explicit AC-053 period_not_submittable code and
  preserve field feedback after its HTTP code regression. F01 also checks
  authority/state before pending-only edit scope after the approved-owner
  GET edit regression returned 404 instead of AC-084's required 409.
  F06 also rejects unavailable approved-upload source storage before any
  first-submit snapshot after AC-052's HTTP RED incorrectly returned 200.
  F24 probes maximum-length text in actual PDF coordinates and text runs;
  F14 is limited to continuation rows/wrapping if those public probes fail.
  F10 broadens only the two PKP array-key segments after the dotted-index
  raw-whitespace HTTP RED bypassed validation with a 200 save.
- **Packet 5C scope:** F31, F32, F33, F30; evidence/CI recovery gates.
  The user authorized continuation of the proposed two-file baseline
  rollback repair on 2026-10-07: F39 corrects the rank table name and F40
  preserves MySQL FK index support. Verify only disposable databases.

| Task ID | Description (files and TDD steps) | Ref ID | Completed | Date |
| --- | --- | --- | :---: | :---: |
| TASK-501 | PROBE: identity read races | TEST-002 | [x] | 2026-10-07 |
| TASK-502 | GREEN: F08 consistent read | TEST-002 | [x] | 2026-10-07 |
| TASK-503 | RED: UI refresh/500 rows | TEST-002 | [x] | 2026-10-07 |
| TASK-504 | GREEN: proven gaps | TEST-002 | [x] | 2026-10-07 |
| TASK-505 | VERIFY: SQL and upload | PRN-002 | [x] | 2026-10-07 |
| TASK-506 | VERIFY: migration recovery | PRN-002 | [x] | 2026-10-07 |
| TASK-507 | VERIFY: full gates/audits | PRN-002 | [x] | 2026-10-07 |
| TASK-508 | GREEN: realized AC evidence | TEST-002 | [x] | 2026-10-07 |
| TASK-509 | VERIFY: docs/scope | PRN-002 | [x] | 2026-10-07 |
| TASK-50A | APPROVAL: user approves completion/re-review | PRN-001 | [ ] | |

The identity race uses independent committed workers and controlled barriers
around the authoritative read. Verify PostgreSQL 16 and MySQL 8 behavior,
including an update committed before the read and one after the snapshot.
Do not use SQLite or a mock as deployment concurrency evidence. If existing
behavior passes, record the successful characterization. If it fails,
choose the smallest consistent-read strategy for both engines. Changes to
legacy history-writing controllers require an explicit scope update first.

Browser tests use real sessions/CSRF and no response stubs. Cover correction
creation/approval, immutable R1 evidence, identity refresh/resubmit, frozen
lateness display, and 500 distinct activities saved and reloaded without
lost values/order. Report normal-motion visual QA separately from tests that
disable animations for deterministic interaction.

F33 maps every AC-001..090 to actual test names, seams, command/result
evidence, and pass/blocked/unverified status. Mark implementation-plan
checkboxes only with their matching evidence. Do not re-label omitted
scenarios as passed because the existing suite count is green.

Re-run real Nginx/PHP-FPM limits, supported qpdf runtime readiness, and
baseline/additive migration up/down/up on disposable named databases.
Document PG UUID bootstrap compatibility. Verify stack shutdown without
removing unrelated containers or shared volumes. If Docker is unavailable,
retain that gate as unverified rather than declaring the phase complete.

Audits must report exit status and distinguish newly introduced dependencies
from existing advisories. Do not force dependency upgrades into this plan.
Retain a separate pre-release decision for reachable existing high/critical
findings. No production migration or release is authorized here.

### Phase 5 Execution Evidence: 2026-10-07

The user approved Phase 5. Functional/runtime probes are complete except
full inherited baseline recovery, which initially failed. The user
authorized the proposed two-file repair through continuation on 2026-10-07.
Both full baseline up/down/up cycles now pass. TASK-506 is closed; TASK-50A
remains the explicit completion/re-review approval checkpoint.

Observed public REDs and minimal GREENs:

- PostgreSQL approval after a history-read barrier returned 409 instead of
  approving its consistent prior snapshot. The atomic history statement
  corrected that probe. A second committed-worker probe exposed a stale
  preloaded employee name; including employee fields in the same statement
  corrected the full identity set without editing history controllers.
- A 500-row tree saved through authenticated HTTP rendered zero editable
  rows after reload. F12 initializes stable client-only Alpine keys.
- Refresh/resubmit rewrote frozen deadline precision. A microsecond HTTP
  regression also found truncated first-submit time. F06 stores initial
  microseconds explicitly and preserves existing raw snapshot values.
- A soft-deleted linked employee produced a 500 during mutation lookup.
  F06 loads that row with trashed records only to let the existing policy
  deny the actor with 403; no global authorization or exception bypass.
- Future submit returned validation_failed instead of period_not_submittable.
  F01/F06 restore the business code with period-field feedback.
- Approved-owner GET edit returned 404 instead of revision_not_editable
  409. F01 checks authorization/state before pending-only edit scope.
- Missing stored external source returned successful first submit. F06
  checks availability before freezing any first-submit metadata; recovery
  resubmits the same draft after the file is restored.
- Literal dots in JSON array keys bypassed raw decimal whitespace rejection
  and saved with 200. F10 broadens only the PKP key segments; other modules'
  trimming behavior remains characterized unchanged.
- Maximum text emitted PDF baselines below A4 and a 5,000-character notes
  run outside page width. F14 uses complete continuation rows and wrapped
  notes at unchanged font sizes. A maximum-row header probe then found an
  orphan first header; smaller continuation fragments retain repeated
  main headers as well as appendix headers. Actual qpdf coordinates/text
  and independent Poppler renders verify the final output.

Already-green boundary/compound cases are characterization, not invented
RED. They cover maximum deployment counts/text/numbers, malformed decimal
forms, all rating thresholds, equal indicator weighting, ineligible actors,
real positive global Gate denial for unassigned content/document actors,
nonpositive mutation versions, both exact month-end source paths, revoked
selection, source-mode corrections, first-submit failure/recovery, numeric
resubmit, external declaration reset, and signed R1/R2 completeness.

Current gates:

- Full PHP: 595 passed, 4,557 assertions, 86.46 seconds.
- PostgreSQL 16: 12 passed, 104 assertions, 11.538 seconds.
- MySQL 8.0: 12 passed, 104 assertions, 77.582 seconds.
- Browser PKP: 12 passed in 1.1 minutes, including 500 distinct UI rows,
  real history-update/refresh/resubmit, and normal-motion desktop/mobile QA.
- Base browser: two passed in 3.6 seconds.
- Nginx/PHP-FPM envelope: one passed, 33 assertions, 1.488 seconds.
- Additive PKP migration up/down/up: passes on both isolated engines.
- Full baseline reset initially exited 1 on inherited rank-table down naming
  and MySQL FK-support index removal. Both defects were repaired after
  authorization; fresh/reset/up passes on both disposable databases.
  CI mechanically enforces these commands; hosted CI has not been run.
- qpdf: 12.2.0 in FPM, Apache, and workspace; host is 12.4.2. No fixed
  supported-major assumption or missing-binary skip was introduced.
- Composer/npm audits: exit 1 with existing findings; no dependency changes.
  Composer repository metadata freshness is partly unverified after a
  Packagist timeout/cache fallback. Existing high findings need separate
  pre-release disposition, not a broad upgrade in this packet.

The 90-criterion inventory records 102 exact realized test names and direct
proof for every functional criterion. Its initial seven partial compound
facets were closed by explicit assertions. Functional PASS does not mean
baseline recovery, CI, or production release is green. Original RED/VERIFY
checkboxes are reopened where original packet proof is not retained or the
original packet proof is not retained; no historical evidence is invented.

The authorized two-migration repair corrects the down table name and
renames MySQL FK support rather than dropping its last supporting index.
Formatting passed 274 files, Markdown passed five artifacts, and scoped
diff/syntax checks passed. All five named stack containers are verified
exited, as are the two named qpdf probes with exit 0; no volumes were
removed. The latest maximum-text render is eight A4 landscape pages with
repeated headers; maximum unbroken notes retain all 5,000 characters.
Normal-motion forms were visually inspected on desktop and mobile.
These checks and the unchanged spec hash are recorded in
`docs/pkp-verification.md`. No staging, commits, push, deployment, production
migration, shared-volume removal, or unrelated container stop is authorized.

## 3. Structural Remedies & Alternatives

- **ALT-001:** Supply the required attempt in the existing context rather
  than weakening policy checks to permit every assigned revision. Historical
  reads need explicit scoped evidence, not unconditional assessor access.
- **ALT-002:** Use a narrow PKP failure adapter/handler where duplication
  warrants it. Avoid a global Handler/Gate rewrite. Preserve generic error
  messages and action-specific field feedback.
- **ALT-003:** Separate official/pending read contexts in the existing detail
  contract. A new general repository/service framework is unnecessary.
- **ALT-004:** Render the exact appendix from stored rational snapshots,
  not floats, re-calculated draft rows, or larger decimal approximations.
- **ALT-005:** Choose identity snapshot isolation only after a deterministic
  deployment-engine probe. Atomic-read or transaction-snapshot approaches
  may be viable; assess lock ordering and both engines before selection.
- **ALT-006:** N+1 tree batching, independent exception autoloading, and
  vertical commit grouping are optional review recommendations. Keep them
  separate unless a required regression proves they are necessary.

## 4. Files Affected

All paths are relative to `/Users/macbook/Developer/php/simpeg`.
Files marked new are proposed and may be created only when their packet
demonstrates the need. Do not replace whole source files.

- **F01:** `app/Http/Controllers/PkpController.php`
- **F02:** `app/Services/PkpDocumentService.php`
- **F03:** `app/Policies/PkpPolicy.php`
- **F04:** `app/Services/PkpPdfValidator.php`
- **F05:** `app/Services/PkpPdfService.php`
- **F06:** `app/Services/PkpSubmissionService.php`
- **F07:** `app/Services/PkpAssessmentService.php`
- **F08:** `app/Services/PkpIdentityService.php`
- **F09:** `app/Http/Requests/SavePkpRequest.php`
- **F10:** `app/Http/Middleware/TrimStrings.php` (PKP-scoped only if needed)
- **F12:** `resources/views/pkp/edit.blade.php`
- **F13:** `resources/views/pkp/partials/scores.blade.php`
- **F14:** `resources/views/pkp/pdf.blade.php`
- **F15:** `resources/views/pkp/assessments.blade.php`
- **F17:** `tests/Feature/PkpDocumentUploadTest.php`
- **F18:** `tests/Unit/PkpPdfValidatorTest.php`
- **F19:** `tests/Feature/PkpDocumentFailureTest.php`
- **F20:** `tests/Feature/PkpDraftTest.php`
- **F21:** `tests/Feature/PkpActivityTest.php`
- **F22:** `tests/Feature/PkpRevisionTest.php`
- **F23:** `tests/Feature/PkpHistoryTest.php`
- **F24:** `tests/Feature/PkpPdfTest.php`
- **F25:** `tests/Feature/PkpScoreDisplayTest.php`
- **F26:** `tests/Feature/PkpAssessmentTest.php`
- **F27:** `e2e/pkp.spec.js`
- **F28:** `tests/Integration/Pkp/PkpWorkflowRaceTest.php`
- **F29:** `tests/Support/PkpConcurrencyHarness.php`
- **F30:** `.github/workflows/pkp-integration.yml`
- **F31:** `docs/pkp-verification.md`
- **F32:** `plan/plan-monthly-pkp.md` (evidence/checklist maintenance)
- **F33:** `docs/review/monthly-pkp-ac-evidence.md` (new evidence matrix)
- **F34:** `app/Services/PdfValidationLimit.php` (new if required)
- **F36:** `resources/views/pkp/partials/history.blade.php` (new if needed)
- **F37:** `app/Http/Middleware/PkpErrorResponses.php` (new route adapter)
- **F38:** `routes/web.php` (PKP-scoped middleware registration)
- **F39:** `database/migrations/2025_02_12_150710_create_riwayat_pangkats_table.php`
- **F40:** `database/migrations/2026_02_10_004802_add_indexes_to_izin_and_cuti_tables.php`
- **F41:** `database/seeders/PkpE2EFixtureSeeder.php` (test-only permissions)

Update `docs/ARCHITECTURE.md` only when realized public seams/modules change;
its topology must match the final implementation. Existing test-stack/runtime
files may require evidence-only updates; expand executable file scope
explicitly before changing those recipes. Do not alter unrelated lockfiles,
Izin refactor artifacts, employee data, or production configuration.

## 5. Rollback / Recovery Plan

- **RBCK-001:** Record the scoped baseline and existing dirty work before
  execution. Revert only authorized remediation hunks; never reset/clean
  the shared working tree or erase unrelated changes.
- **RBCK-002:** Preserve all bound/current/archived files, attempt snapshots,
  decisions, and reviews. Compensation may remove only an unreferenced,
  server-generated new staging path after a proven failed transition.
- **RBCK-003:** Keep the approved signed-external replacement matrix and
  accepted-A retention. A read/UI repair must not change content/document
  approval semantics or allow archive mutations.
- **RBCK-004:** Verify migration rollback only on disposable named databases.
  Retain actual up/down/up results. Do not run production migration commands
  or remove shared Docker volumes during this review/remediation workflow.
- **RBCK-005:** If a required gate fails, retain failure evidence and mark
  completion blocked/unverified. Never skip the test, change the oracle,
  suppress the error, or lower `CONSTRAINTS.md` to obtain a green result.
- **RBCK-006:** After this plan is approved, invoke:

```text
/tdd-write-code Execute @plan/plan-refactor-monthly-pkp-v1.0.md
against @spec/spec-feature-monthly-pkp.md, following RED-GREEN-VERIFY.
```

## Resumed Baseline Recovery Evidence: 2026-10-07

The repeated RED commands are recorded in
`/tmp/pkp-phase5-resumed-pg-red.log` and
`/tmp/pkp-phase5-resumed-mysql-red.log`. Both exited 1 for their expected
inherited rollback defects before any migration source edit.

F39 changes only the down table name. F40 renames the last MySQL supporting
index back to the FK-support name when no alternative support exists;
other engines and already-supported MySQL indexes retain the original drop.
Both named disposable databases pass fresh/reset/up after these edits.
Logs are `/tmp/pkp-phase5-resumed-{pg,mysql}-{up,down,reup}.log`.
No production migration, dependency upgrade, staging, or commit occurred.

The resumed full PHP suite passed 595 tests and 4,557 assertions in 87.61
seconds after the two migration repairs. Pint passed 274 files, both
migration syntax checks passed, and all five owned stack containers are
verified exited without volume removal. Local Phase 5 execution gates are
complete; TASK-50A remains the user's completion/re-review checkpoint.
