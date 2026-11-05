# TDD Clarification Report [Review Iteration 3]

> [!SUCCESS]
> REMEDIATION STATUS: RESOLVED
> On 2026-10-05, the author remediated `spec/spec-feature-monthly-pkp.md`
> to version 0.2 using confirmed decisions and the PROCEED resolutions.
> Projected readiness: **92/100** (Completeness 38/40, Clarity 28/30,
> Alignment 26/30), with no remaining critical contract veto identified.
> Historical scores and findings below are retained for traceability.
> This is author self-assessment, not independent review, implementation
> test success, or approval of the Draft. See the closure evidence below.

**Target Document:** `spec/spec-feature-monthly-pkp.md` (version 0.1, Draft)

**Audit Date:** 2026-10-05

**Readiness Score:** 79/100

**Status:** Below Threshold (< 80); specification remediation required

**User Decision:** PROCEED after iteration 3; remaining minor decisions
delegated and recorded in Section 3. Handoff is to specification
remediation, not implementation or approval of the unchanged draft.

**Score Breakdown (Quality Gate Rubric):**

- **Completeness (max 40):** 32. The two authoring paths, approval,
  scoring, printing, private uploads, and history are covered. Revision
  creation, reassignment, and deadline contracts are incomplete or absent.
- **Clarity & Testability (max 30):** 23. Scoring has independent examples,
  but current-version selection, identity eligibility, input limits,
  and several failure responses are not deterministic in the document.
- **Alignment & Constraints (max 30):** 24. The main design follows the
  user brief and repository conventions. The no-deadline assumption and
  reassignment restriction contradict subsequent user decisions. New
  domain definitions have not yet been incorporated in the glossary.
- **Critical Flaw Veto:** Triggered (maximum 79). The specification
  promises revision creation without an executable public contract.

The score evaluates the current specification, not a hypothetical
remediated version. User decisions recorded below do not mean the
specification has already been updated or approved. Iteration 1 scored
74/100 (30/40 completeness, 21/30 clarity, 23/30 alignment).
Iteration 2 scored 79/100. Iteration 3 rechecks the specification after
the refinement decisions below and remains 79/100: those decisions are
recorded in this report, but the specification has not been remediated.

## 1. Critical Findings (Untestable Blockers)

### CF-001: Revision Creation and Official-Version Selection

- **Requirement / Section:** Section 4.3 states, "correction creates
  another revision requiring approval."
- **Testability Gap:** No route or complete service contract creates a
  correction. The document does not specify how the official revision
  is selected while another revision is being prepared or assessed.
- **Confirmed Boundary:** The previous approved revision remains official
  until the replacement is approved. Approval of the replacement makes
  it official. If its final signed document is missing, current monthly
  completeness becomes awaiting that document; the older signed document
  remains an archive and cannot complete the new revision.
- **Proposed Seam:** Public revision-create boundary, PKP detail/list,
  decision endpoint, and revision-specific PDF/document download routes.
  The author must define exact request/response contracts and concurrency
  behavior, including simultaneous correction creation and approvals.

### CF-002: Deadline Assumption Contradicts Confirmed Requirements

- **Requirement / Section:** Assumption A4 states, "No submission
  deadline or automatic overdue penalty is imposed"; Section 4.1 has
  no deadline configuration contract.
- **Testability Gap:** No configuration boundary or stored submission
  deadline exists for the now-required lateness indicator.
- **Confirmed Boundary:** Admin configures the deadline. Late submissions
  remain accepted. Classification uses the first successfully accepted
  submission and the deadline effective at that moment. Subsequent
  returns, resubmissions, and revisions do not change that classification.
  Later deadline edits apply to Pegawai who have not submitted yet.
- **Proposed Seam:** Admin deadline configuration, submit endpoint,
  monthly list/detail, and audit history. Specify the exact timezone,
  boundary comparison, missing-configuration behavior, snapshot data,
  and deadline-update/submission race behavior. Do not infer penalties.

### CF-003: Assessor Reassignment Cannot Depend on an Unavailable Actor

- **Requirement / Section:** Assumption A5 states, "Changing assignment
  on submitted content requires return first."
- **Testability Gap:** If the assessor becomes unavailable, the required
  return cannot necessarily occur; no administrative reassignment
  contract or authorization matrix is defined.
- **Confirmed Boundary:** Admin may reassign an unapproved submission
  to an allowed assessor, with a mandatory reason and recorded history.
  The previous assessor immediately loses decision authority. Completed
  approvals retain their historical assessor.
- **Proposed Seam:** Admin reassignment boundary and decision endpoint.
  Define exact eligibility, expected-version checks, and the outcome of
  concurrent approval and reassignment. Do not copy Izin's admin approval
  bypass into PKP; administrative reassignment is not assessor approval.

### CF-004: Empty-Indicator Validation Must Distinguish Authoring Paths

- **Requirement / Section:** Section 3.2 says, "Reject submission with
  zero indicators or an indicator with zero activities." AC-021 is
  unqualified, while AC-022 allows external PDF submissions without them.
- **Testability Gap:** A single validation policy can both reject and
  accept the same external payload under these statements.
- **Required Remediation:** Qualify activity completeness and calculator
  preconditions as application-source rules. External-source PKP requires
  a readable PDF, period, and eligible assigned assessor, without activity
  entry or a computed score. Its numeric score remains unavailable.
- **Proposed Seam:** Source-specific Form Request validation exercised
  through submission routes; test both paths with absent indicators.

### CF-005: Historical Identity Is Required but Selection Is Unspecified

- **Requirement / Section:** Section 3.1 calls for a "Historical identity
  snapshot" but defers effective-date selection to implementation.
- **Testability Gap:** Two implementations may select different job or
  rank histories and generate different approved documents.
- **Confirmed Boundary:** Rank, position, and work unit are selected as
  effective on the last day of the assessed month. Missing required
  history blocks submission while retaining the draft. Admin completes
  the history; the owner does not replace it with manual identity text.
- **Proposed Seam:** Draft/detail validation, submit endpoint, approved
  snapshot, and PDF route. Define tie handling, soft-deleted records,
  and employee-versus-assessor identity snapshot timing explicitly.

### CF-006: Upload Validity and Failure Outcomes Need Public Contracts

- **Requirement / Section:** A2 proposes PDF up to 10 MiB; AC-013 rejects
  unsupported/oversized files without defining structural PDF validity.
- **Confirmed Boundary:** PDF only; maximum 10 MiB per source or final
  file. Reject corrupt, zero-page/empty, or password-protected PDFs at
  upload. The PDF must open without a password and contain at least one
  page. Rejection must not replace the previous usable document.
- **Testability Gap:** Extension/MIME checks alone do not prove this
  boundary. Responses, parser capability, and storage compensation
  must be observable rather than hidden behind a generic failure.
- **Proposed Seam:** Document upload endpoint and private download.
  Specify field errors and exact byte limit (10,485,760), with fixtures
  for valid, corrupt, encrypted, renamed, empty, and oversize inputs.

## 2. Resolved Items & Pre-Agreed Boundaries

These decisions were explicitly supplied by the user. They are confirmed,
not automatically resolved assumptions. Items already in the specification
are retained here to provide complete remediation context.

### Decisions Already Reflected in Specification Version 0.1

- **Workflow:** In-application assessment and approval by exactly one
  Pejabat Penilai. Application-source PKP is approved, printed, signed
  outside the application, and uploaded as a revision-bound final file.
  **Seams:** Submit, decision, approved PDF, and signed upload endpoints.
- **Assessment authority:** The application calculates achievement;
  assessors cannot override numeric scores. They approve or return for
  correction. **Seams:** Calculator and decision/validation endpoints.
- **Aggregation:** Every activity contributes to its indicator average;
  the overall score equally averages indicators regardless of activity
  count. Do not reproduce workbook formulas that omit activities.
  **Oracle:** Indicator scores 80 and 100 produce overall 90.
- **Cap:** Cap each activity score at 100 before aggregation. Preserve
  actual realization and credit. **Oracle:** Scores derived from target 5,
  realization 6 and target 5, realization 3 average to 80, not 90.
- **No rounding:** Classification uses unrounded values. For example,
  90.99 is Baik and 90.991 is Sangat Baik. The repeating-decimal proposal
  in version 0.1 was subsequently confirmed during refinement below.
  **Seams:** Calculator, score snapshot, screen, and PDF.
- **External path:** Upload PDF with period and assessor; no structured
  activity entry, OCR, or score extraction. The same approval stage
  applies. **Seams:** External create/submit, review, and download.
- **Assessor selection:** Admin sets a default and allowed alternatives;
  the owner may choose an allowed alternative. **Seams:** Settings,
  draft/save, and submit validation.
- **Signed external reuse:** A source PDF declared signed becomes final
  only after assessor approval of that exact file. No duplicate upload
  is required. **Seams:** Upload declaration, decision, final-file binding.

### Decisions Clarified During This Audit

- **Official revision:** Old approval remains official while a correction
  is pending; an approved replacement becomes official immediately.
  **Seams:** Revision creation, list/detail, and approval transaction.
- **Signed completeness after correction:** The old signed file cannot
  complete an approved replacement; show awaiting the new signed file.
  **Seams:** Monthly completeness and revision-specific download.
- **Unavailable assessor:** Admin reassigns unapproved submissions with
  reason and history; old decision authority ends immediately.
  **Seams:** Administrative reassignment and assessor decision routes.
- **Upload envelope:** PDF only, at most 10 MiB for source and final.
  **Seams:** Upload validation and preservation of the previous file.
- **Deadlines:** Admin configures deadlines; late submissions remain
  accepted and visibly marked. **Seams:** Configuration and monthly list.
- **Lateness event:** Use the first successful submission, not later
  corrections or revisions. Failed submissions do not establish it.
  **Seams:** Submit validation/transaction and resubmission.
- **Deadline history:** Snapshot the deadline at first submission; later
  edits apply to Pegawai without a prior successful submission.
  **Seams:** Configuration/submit race and immutable history.
- **Identity date:** Use rank, position, and work unit effective at the
  assessed month's end. **Seams:** Submission snapshot and approved PDF.
- **Missing history:** Preserve draft and block submission until admin
  completes required history. **Seams:** Validation and subsequent submit.
- **Readable PDF:** Reject corrupt, empty/zero-page, and password-protected
  files before accepting an upload. **Seams:** Real PDF fixtures at upload.

### Additional Confirmed Decisions During Refinement

The user chose REFINE after saving iteration 2. The score remains an
assessment of the unchanged specification; these additional decisions
must also be incorporated by the specification author.

- **Unset deadline:** Accept submission and display "Deadline not set".
  Classify neither on time nor late. A subsequently configured deadline
  does not retroactively classify that first submission.
  **Seams:** First submission, deadline configuration, and monthly detail.
- **Deadline timezone and equality:** Use Asia/Makassar. A submission
  accepted exactly at the deadline is on time; one after it is late.
  **Seams:** Controlled-clock submit tests at and after the boundary.
- **Deadline scope:** One configured deadline per assessed calendar month
  applies to all Pegawai; it is not individually configured per Pegawai.
  **Seams:** Admin configuration and submissions from multiple Pegawai.
- **Period eligibility:** Current and past months may be submitted.
  Future-month drafts may be prepared, but cannot be submitted.
  No retrospective cutoff was selected. **Seams:** Create/save/submit
  across month boundaries using the application clock.
- **Identity changes before approval:** If rank, position, or work unit
  applicable to the assessed period changes, correct the submission and
  resubmit before approval. Preserve the original first-submission event
  used for lateness. **Seams:** Identity snapshot, resubmit, and approval.
- **Approval timing:** Current-month PKP may be submitted, reviewed, and
  returned, but approval is allowed only after that month ends in
  Asia/Makassar. **Seams:** Decision endpoint immediately before and at
  the first instant of the following month, for both authoring paths.
- **Signed-final inspection:** A signed PDF uploaded after content
  approval awaits the Pejabat Penilai's document inspection. The assessor
  accepts or returns it before final completeness; the inspection never
  changes calculated scores or the existing content approval.
  **Seams:** Signed upload, document decision, monthly completeness, and
  immutable assessment snapshots. Define a separate public document
  decision contract during remediation.
- **Comparable activity units:** Target and realization use one shared
  unit per activity. Reject mismatched unit payloads; do not calculate
  a ratio between quantities expressed in different units.
  **Seams:** Activity save/submission validation, calculator inputs,
  and screen/PDF rendering of the same unit in both workbook columns.
- **Repeating-decimal presentation:** Display six truncated decimal digits
  followed by an ellipsis, with the exact fraction available in details.
  Example: `66.666666...`, exact value `200/3`. Apply consistently on screen
  and PDF; calculation and predicate classification use exact values,
  never the truncated preview. Terminating decimals retain exact values.
  **Seams:** Calculator result representation, detail view, and PDF.

An external source declared signed remains governed by its previously
confirmed same-file approval/final binding. This refinement does not
silently add a second inspection of that already-reviewed source PDF.
If the original assessor is unavailable during post-approval document
inspection, admin may assign a replacement from the allowed assessor
list with mandatory reason and assignment history. Replacement authority
is limited to document inspection; the original content approval actor,
approved score, and approved identity snapshot remain unchanged.
The document-decision and administrative-assignment public boundaries
must enforce and test this separation of authority.

## 3. Assumed / Auto-Resolved / Out of Scope (The 20% Tail)

The user selected PROCEED after iteration 3. The resolutions below are
explicitly delegated technical judgments, not direct user choices. They
complete clarification inputs but do not edit or approve the specification.
The earlier open-item inventory remains below for remediation traceability.

### Delegated Resolutions Following PROCEED

- **Monthly uniqueness and mode:** `[Assumed / Auto-Resolved]` One PKP per
  Pegawai/calendar month. Duplicate creation returns conflict and links
  to the existing record. Source is immutable within a submitted revision;
  another source can be chosen for a new correction revision. Permit only
  one editable or submitted correction per monthly record at a time.
- **Official and pending selection:** `[Assumed / Auto-Resolved]` Maintain
  separate official-approved and pending-revision bindings. Approved
  replacement selection and completeness reset occur in one transaction.
  Reject stale changes with 409; never select by latest creation date alone.
- **Returned revision evidence:** `[Assumed / Auto-Resolved]` Capture each
  submitted payload, source file binding, assessor assignment, and decision
  as immutable historical evidence before editable returned changes.
  Resubmission must not rewrite what an assessor previously reviewed.
- **Deadline configuration:** `[Assumed / Auto-Resolved]` Admin configures
  one year/month record with a nullable second-precision local timestamp,
  version, and audited changes. Store the equivalent UTC instant. Snapshot
  its value or absence on the first accepted submission. Lock settings
  and the monthly record consistently during configuration/submit races.
  Comparison is submission time <= deadline; classification never uses
  browser time. Boundaries are interpreted in Asia/Makassar.
- **Account eligibility:** `[Assumed / Auto-Resolved]` A non-soft-deleted
  Pegawai must have a non-soft-deleted, verified linked User whose status
  is 1. Apply eligibility at privileged mutations and submission/decision.
  Reject self-assignment. Do not treat status_pegawai as an active flag:
  its validated values are CPNS, Hakim, PNS, PPPK, and PPNPN.
- **Authorization:** `[Assumed / Auto-Resolved]` Owners create, edit,
  submit, and upload their records. Assigned content assessors review and
  decide content; assigned document inspectors decide signed files.
  Admin/super-admin may view organisation records and configure deadlines,
  assessor lists, and authorized reassignment, but may not approve content
  or inspect documents solely by being admin. Require actual assignment.
  All readers may download only the records their policy permits.
- **Historical identity:** `[Assumed / Auto-Resolved]` Select the latest
  non-deleted history effective on or before the assessed month's end:
  RiwayatJabatan.tanggal_mulai and RiwayatPangkat.tmt. Resolve position
  through jabatan_uuid and work unit through satuan_kerja. Equal effective
  dates with conflicting values block submission rather than pick a UUID.
  Snapshot employee and assessor display identity for the approved print;
  record document inspector identity separately. Approval verifies that
  applicable employee history still matches the submitted snapshot.
- **Draft completeness:** `[Assumed / Auto-Resolved]` Incomplete fields
  may be null in drafts; submission requires complete application-source
  activities. External-source records have no required activity rows.
  Preserve prior approved snapshots when any history changes later.
- **Input limits:** `[Assumed / Auto-Resolved]` Period years 1900..9999,
  months 1..12; at most 50 indicators and 500 activities per revision.
  Indicator titles <= 500 characters, descriptions <= 2,000, units <= 100,
  and notes/reasons <= 5,000. Blank return/reassignment reasons are invalid.
  Target/realized quantities have at most four fractional digits and
  fit decimal(14,4); credit has at most six and fits decimal(14,6).
  Quality fits decimal(10,4) and lies in 0..100. Reject excessive scale,
  exponent notation, NaN, infinity, negative quantities, and submit-time
  zero targets. Never round invalid input to make it fit storage.
- **Exact arithmetic:** `[Assumed / Auto-Resolved]` Use the installed
  brick/math BigRational capability for exact ratios and aggregation;
  promote it to an explicit runtime requirement during implementation
  if used directly. Store normalized numerator/positive-denominator
  strings and calculator version. Verify the library API in authoring;
  do not assume its installed version proves all runtime contracts.
- **Public calculator seam:** `[Assumed / Auto-Resolved]` Calculator input
  consists of ordered validated decimal-string activities grouped by
  indicator. Output includes exact activity, indicator, and overall
  fractions plus predicate and nonauthoritative display strings.
  Empty groups or invalid units are errors, never zero-score substitutes.
  The specification author supplies exact PHP DTO/signature contracts.
- **Display:** `[Assumed / Auto-Resolved]` Indonesian display uses decimal
  comma; machine payloads use invariant decimal point strings. Screen and
  PDF show the exact terminating decimal or six truncated repeating
  digits with ellipsis. Provide the exact fraction in a detail section
  or PDF calculation appendix; never replace it with a rounded value.
- **Document inspection:** `[Assumed / Auto-Resolved]` Post-approval
  uploads move through pending/accepted/returned document decisions.
  Return requires a reason and permits owner replacement. Bind decisions
  to document UUID/hash and approved revision. A stale inspector cannot
  accept a replaced file. Upload alone cannot satisfy completeness.
  Inspection is human verification, not cryptographic authentication.
- **Final-file replacement:** `[Assumed / Auto-Resolved]` Keep an already
  accepted final file current while its replacement awaits inspection.
  Acceptance atomically switches the binding; return preserves the old
  accepted file. A file from an older content revision never completes
  the current official revision.
- **PDF validation failures:** `[Assumed / Auto-Resolved]` Invalid files
  produce field-level errors (422 for JSON) without metadata replacement.
  Stage private files and compensate failed metadata commits. Validator
  unavailability fails visibly, not as a successful upload. A bounded
  parser must prove readability, pages, and absence of password protection;
  DomPDF alone does not establish this capability. Identify and verify
  parser choice and runtime needs during specification remediation.
- **Retention:** `[Assumed / Auto-Resolved]` No automatic purge of records,
  decisions, submitted snapshots, or superseded referenced documents.
  Remove unreferenced failed-upload staging files only after proving they
  are unbound. Formal retention-duration policy is backlog and must not
  silently delete approved evidence.
- **Print scope:** `[Assumed / Auto-Resolved]` Official printing is limited
  to approved revisions; reject unapproved print requests. Draft preview
  is excluded from this version. Use A4 landscape for application-source
  PKP, repeat table headers across pages, avoid clipped activities/values,
  and retain identity, indicator summaries, predicate, and signature block.
  External printing downloads the exact approved source PDF unchanged.
- **Failure semantics:** `[Assumed / Auto-Resolved]` JSON validation 422,
  unauthorized 403, missing/nested-mismatched resources 404, stale or
  state-conflicting transitions 409. Web validation uses redirects with
  field errors and preserved inputs. Storage/database failures must be
  visible, preserve accepted state, and record diagnostic details without
  exposing private paths or document contents to users.
- **Concurrency environment:** `[Assumed / Auto-Resolved]` Repository
  config defaults to MySQL, while that is not evidence of live production
  configuration. Verify MySQL-compatible locking and uniqueness on an
  isolated test database for the planned path. If the deployment uses
  another supported engine, exercise that engine before claiming coverage.
- **Statistics:** `[Assumed / Out of Scope]` External scores remain
  unavailable, not zero. No additional analytics dashboard or numeric
  score extraction is introduced by this audit.

### Earlier Open-Item Inventory

This inventory records gaps in the unchanged specification. Section 3's
delegated resolutions now settle the clarification inputs for these
items; the author must translate them into executable contracts. Runtime
PDF parser and deployment-engine capabilities still require verification.

- **Monthly uniqueness:** A1 proposes one PKP per Pegawai and month.
  Revision decisions support that structure, but duplicate and source-mode
  change behavior still need explicit contracts and acceptance criteria.
- **Deadline contract:** Scope, timezone, equality, and unset behavior are
  now confirmed above. Exact payloads, history storage, race outcomes,
  and observable responses still require specification remediation.
- **Actor eligibility:** "Active Pegawai" and "active linked User" require
  concrete predicates, including suspension and soft deletion. An assessor
  being assigned must not imply unrelated Izin role requirements.
- **Authorization matrix:** Admin viewing, editing, printing, uploading,
  and decision privileges are not completely enumerated. Configuration
  and reassignment authority must not silently confer score overrides.
- **Draft schema:** Proposed activity columns look mandatory while drafts
  can be incomplete. Define nullable draft storage versus submit validation.
- **Input bounds:** Decimal scale/range, field lengths, indicator/activity
  counts and invalid numeric syntax remain provisional. Shared target and
  realization units are now confirmed above.
  Inputs must not silently round merely to fit proposed decimal columns.
- **Exact arithmetic:** Rational storage is proposed but the public
  calculator input/output types and runtime capability are not fixed.
  Prove exact comparisons and averaging through independent oracles.
- **Presentation:** Six-digit truncated previews with an ellipsis and
  exact fractions in details are now confirmed. Define localization and
  PDF detail placement without losing the exact value or implying rounding.
- **Signed-final inspection contract:** Assessor acceptance is confirmed.
  Define its state/payload/errors, upload/replacement permissions, and
  the confirmed replacement assignment when an assessor is unavailable.
  Do not infer
  cryptographic signature authenticity from human inspection.
- **Audit retention:** Returned edits, superseded documents, declaration
  changes, and old submission data need recoverable historical evidence.
  Retention and cleanup behavior have not been approved.
- **Print scope:** "drafts visibly marked if supported" is optional and
  therefore not a deterministic acceptance criterion. Define its scope
  and the required readable layout for long or repeating-value content.
- **Environment:** Production-compatible concurrency verification needs
  an identified database engine. SQLite alone cannot prove locking.
- **Previously explicit exclusions:** Excel import, annual SKP, digital
  signatures, payroll integration, and Izin/Cuti changes are out of scope.

### Repository Evidence and Verification Limits

Read-only inspection used the current specification, root AGENTS.md,
CONTEXT.md, CONSTRAINTS.md, and the clarification report template.
There is no CONTEXT-MAP.md or .codegraph directory in the inspected repo.

- User.pegawai links authenticated User UUID to Pegawai.user_uuid.
- Pegawai has status_pegawai, not a proven generic boolean active field.
- RiwayatJabatan exposes tanggal_mulai, satuan_kerja, and jabatan_uuid;
  RiwayatPangkat exposes tmt and pangkat_golongan. Effective-date rules
  must name these actual fields and resolve historical ties.
- IzinPolicy permits some admin approval bypasses and uses Izin-specific
  roles. PKP's assigned-assessor rules must not inherit those implicitly.
- IzinDocumentService demonstrates private local storage and MIME checks,
  but includes legacy public fallback and no structural PDF validation.
  Its existence does not prove PKP's stronger upload requirements.
- New PKP terms are proposed in the specification, but absent from the
  current root glossary. No new glossary term was resolved in this audit.

No source code or specification was modified by this clarification phase.
No terminal commands, application tests, migrations, or linters were run
during the audit. Prior specification lint success is not validation of
this newly created report or proof that the feature is implemented.
Additional read-only checks inspected config/database.php, composer.lock,
the users migration, and Pegawai/User controllers. User.status accepts
0/1 and defaults to 1. The installed brick/math version is 0.12.3.
The database config defaults to MySQL; no live database was inspected.

## 4. Next Steps

### Remediation Closure Evidence (Specification Version 0.2)

- **CF-001 resolved:** Section 4.2 defines correction creation and
  expected-version conflicts. Sections 4.4..4.5 define official/pending
  bindings and atomic approval switches. AC-044..047 cover old official
  continuity, racing creates, replacement completeness, and history.
- **CF-002 resolved:** Deadline configure/read contracts, UTC snapshots,
  first-submission classification, sentinel lock ordering, unset behavior,
  and inclusive Asia/Makassar boundary are explicit. AC-048..054 and
  AC-080 cover configuration, failures, equality, and races.
- **CF-003 resolved:** Content and document-inspector reassignment are
  separate public routes with permissions, reason/history, allowed-set
  checks, and unchanged approval attribution. AC-058..063 and AC-072
  exercise revoked authority, concurrency, and final-file decisions.
- **CF-004 resolved:** Section 3.2/4.3 and AC-021 explicitly scope activity
  completeness to application source; AC-022..026 and AC-070 cover
  external PDF-only submissions without inferred numeric scores.
- **CF-005 resolved:** Section 3.1 names actual historical fields,
  month-end selection, missing/tied data, and immutable identity. The
  refresh-identity route closes changed attempts before resubmission.
  AC-055..057 cover identity selection and approval refusal on changes.
- **CF-006 resolved:** qpdf validation contract specifies clean structural
  checks, password/page-count semantics, process bounds, failure outcomes,
  staged storage, and preservation of prior files. AC-064..067 cover
  parser results and real fixtures; Code runtime-readiness stays unproven.
- **Confirmed refinements incorporated:** Document inspection/acceptance,
  replacement inspector, period eligibility, shared units, and truncated
  repeating presentation with exact fraction details have public contracts
  and AC-058..078. The existing signed external-source reuse rule remains.
- **Delegated resolutions incorporated:** Sections 3..7 define bounds,
  null drafts, exact calculator/DTO output, authorization, retention,
  printed layout, input grammar, errors, and test isolation. Section 6.2
  maps all AC-001..080 to public test seams and mocking limits.

Repository verification also found compose.prod.yaml explicitly selects
PostgreSQL 16, while config/database.php and Sail use a MySQL default.
Version 0.2 therefore requires PostgreSQL 16 plus MySQL 8 concurrency
coverage in isolated databases and verification of the actual release
engine. No production engine/runtime health is inferred from those files.

The qpdf and brick/math capabilities were checked against official qpdf
documentation and tagged brick/math 0.12.3 source linked in spec Section
9.2. qpdf is not available on the inspected host PATH; local vendor is
absent. Runtime provisioning and real parser/database tests are explicit
Code-phase gates, not silently completed checks. Root glossary integration
remains a documentation follow-up under the spec-only scope.

### Historical Handoff and Current Authoring Follow-up

1. Use `/tdd-spec` with both the current specification and this report.
   Incorporate all confirmed decisions and resolve Section 1 contracts;
   do not ask the user to repeat decisions already recorded here.
   Incorporate the explicit delegated resolutions from Section 3 with
   their provenance retained. No additional user answers are required
   for those minor choices unless authoring uncovers a genuine conflict.
2. Define public endpoints, payloads, authorization, observable failures,
   current-revision selection, deadline snapshots, and concurrency rules.
   Complete acceptance criteria and map them to public test seams.
3. Validate the remediated document against CONTEXT.md, CONSTRAINTS.md,
   and applicable documentation standards. Validate Markdown using the
   authoring workflow; this audit does not claim a linter pass.
4. Recalculate readiness after inspecting the actual updated document.
   Do not mark findings resolved based only on a proposed score or intent.
5. Below 80, remediate or request an explicit human override. At or above
   80 without a critical veto, offer PROCEED versus REFINE before planning.
6. Then use `/tdd-analyze` as appropriate and `/tdd-plan-tasks` before
   test-first implementation. This report does not authorize coding.
7. Offer a memory-manager checkpoint for clarified decisions before the
   next phase. Save it only with the user's authorization.

> **User Decision Outcome:** PROCEED selected. Remaining minor questions
> have been explicitly auto-resolved in Section 3. Clarification is ready
> for handoff to `/tdd-spec` remediation. The unchanged specification
> retains 79/100 and its critical veto until its contracts are completed
> and re-audited. This override does not authorize production code.
