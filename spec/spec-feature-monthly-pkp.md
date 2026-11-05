---
title: Monthly PKP Creation, Approval, Printing, and Upload
version: 0.4
date_created: 2026-10-05
last_updated: 2026-10-06
status: Draft
upstream_prd: null
upstream_brief: User request and PCK worksheets supplied on 2026-10-05
target_plan: /plan/plan-monthly-pkp.md
remediation_audit: docs/audit/clarification-report-monthly-pkp-2026-10-05.md
consistency_audit: In-chat independent review of v0.2 on 2026-10-05 (78/100)
plan_audit: docs/audit/consistency-audit-monthly-pkp-plan-2026-10-06.md
---

## Technical Specification: Monthly PKP

### 1. Purpose & Scope

Provide a monthly Penilaian Kinerja Pegawai module where a Pegawai can
upload an externally prepared document or create structured performance
data in SIMPEG. The application supports assessment and approval by one
Pejabat Penilai, PDF printing, and subsequent signed-document upload.

The user selected in-application approval (option C), then a single
Pejabat Penilai approval stage (option A). These are confirmed decisions.
This revision incorporates the audit's confirmed refinements and the
minor choices delegated by the user's PROCEED decision. Status remains
Draft: readiness for planning is not approval to implement the feature.
Version 0.3 also remediates the independent consistency audit recorded
in this chat. That review was not saved as a separate audit file; do not
infer that an absent consistency-audit path exists or has been amended.
Version 0.4 closes the specification rule in the Plan-phase audit's
P-001/D-SPEC-01 using the user's explicit option A on 2026-10-06.
The Plan still needs its own remediation, updated baseline, and approval.

#### 1.1 Out of Scope

Automated Excel import, annual SKP authoring, digital signatures,
payroll integration, and changes to Izin or Cuti are not requested.
This specification does not authorize production implementation.

#### 1.2 Open Questions & Assumptions

> [!IMPORTANT]
> Confirmed decisions and delegated resolutions have distinct provenance.
> All CF-001..CF-006 remediation contracts are specified below. Runtime
> readiness for qpdf and the deployment database remains a Code-phase gate,
> not evidence that implementation or deployment has already passed.

- **CONFIRMED Q1:** The application calculates all achievement scores.
  The Pejabat Penilai cannot override them and may approve or return
  the submission for corrections to target or realization data.
- **CONFIRMED Q2:** Every activity contributes equally to its indicator
  average. The overall score is the equally weighted average of all
  indicator scores, regardless of their activity counts.
- **CONFIRMED Q3:** Externally prepared PKP requires only a PDF, month,
  year, and Pejabat Penilai. It follows the same single approval stage.
  Activity entry, OCR, and score extraction are not required; the
  application does not calculate a score for this path.
- **CONFIRMED Q4:** Admin assigns a default Pejabat Penilai and an allowed
  assessor list for each Pegawai. The owner can select an allowed
  alternative when preparing the PKP. The default must be in that list.
- **CONFIRMED Q5:** Cap each activity achievement score at 100 before
  indicator aggregation. Preserve the actual realized quantity and
  calculated realized credit even when quantity exceeds the target.
- **CONFIRMED Q6:** Do not round activity, indicator, or overall scores.
  Determine predicates from exact unrounded values. Do not apply a
  two-decimal display format that silently rounds the displayed score.
- **CONFIRMED Q7:** An external source PDF declared already signed can
  become the final document upon its Pejabat Penilai's approval without
  another upload. Application-source PKP still requires signed upload
  after approval and printing.
- **CONFIRMED Q8:** Previous approval remains official until a correction
  is approved. Approval switches the official revision; an old signed
  document cannot complete the newly approved revision.
- **CONFIRMED Q9:** Admin can reassign unapproved submissions to allowed
  assessors with a mandatory reason and history. Old authority ends.
- **CONFIRMED Q10:** Source and signed-final uploads accept only PDF,
  maximum 10 MiB, readable without a password and with at least one page.
  Reject corrupt, empty, zero-page, or password-required files.
- **CONFIRMED Q11:** Admin sets one deadline per calendar month for all
  Pegawai. Late submissions are accepted, with no lock or penalty.
- **CONFIRMED Q12:** Lateness uses the first accepted submission and its
  deadline snapshot; corrections and later deadline edits cannot change it.
  Missing deadlines yield deadline_not_set and no retroactive classification.
- **CONFIRMED Q13:** Deadline and period boundaries use Asia/Makassar.
  Submission at the deadline is on time; only later submission is late.
- **CONFIRMED Q14:** Submit current/past months only; future drafts are
  allowed. Approval begins only after the assessed month has ended.
- **CONFIRMED Q15:** Rank, position, and work unit reflect the assessed
  month's end. Missing history blocks submission, preserving drafts.
  Applicable identity changes require correction/resubmission before approval.
- **CONFIRMED Q16:** Signed PDFs uploaded after content approval await
  assessor inspection. Acceptance completes the document; return requires
  correction but does not change content approval or scores.
- **CONFIRMED Q17:** Admin may assign an allowed replacement document
  inspector with reason/history when the original assessor is unavailable.
  Original content approval attribution remains unchanged.
- **CONFIRMED Q18:** Target and realized quantities use the same unit.
- **CONFIRMED Q19:** Show repeating values as six truncated decimals plus
  an ellipsis; exact fractions remain available in screen/PDF details.
- **CONFIRMED Q20:** An approved externally prepared PDF already declared
  signed may receive a replacement signed-final file for unchanged content.
  Inspector acceptance is required; retain the old accepted final until
  replacement acceptance. Target, realization, or assessment changes
  require a new PKP content revision, not this document-only replacement.
- **[Assumed / Auto-Resolved] A1:** One record per Pegawai/month and one
  pending revision. Preserve immutable submission attempts and old approvals.
- **[Assumed / Auto-Resolved] A2:** Eligibility requires non-deleted Pegawai
  and linked verified, non-deleted User with status 1. No Izin role coupling.
- **[Assumed / Auto-Resolved] A3:** Adopt the audit's input bounds, exact
  arithmetic, permissions, retention, failures, and approved-only printing
  as detailed in Sections 3..7. These are delegated choices, not new user
  answers. No automatic deletion or numeric external-score extraction.
- **[Assumed / Auto-Resolved] A4:** Use qpdf >= 11 for structural validation
  with bounded processes. Verify the executable in the deployment runtime;
  no installation or runtime changes are performed in this phase.
- **[Assumed / Auto-Resolved] A5:** Use direct module authorization as the
  authoritative PKP boundary; existing global Gate callbacks may not
  confer ownership/assignment. Administrative configuration does not require
  a Pegawai link. Detailed integration and regression seams follow below.

### 2. Definitions & Domain Model

Existing **Pegawai** and **User** retain their definitions in
`../CONTEXT.md`. New terms below are proposed glossary additions;
the specification-only scope does not modify the root glossary.

**Penilaian Kinerja Pegawai**:
A monthly record of a Pegawai's performance targets, realization,
assessment, and supporting final document, abbreviated PKP.
_Avoid_: SKP, annual performance assessment

**Penilaian Capaian Kinerja Bulanan**:
The monthly performance form shown by the workbook's PCK worksheets.
_Avoid_: Annual PK form

**Pejabat Penilai**:
The person assigned to assess and approve a Pegawai's monthly PKP.
_Avoid_: Pimpinan, Atasan Pimpinan, verifikator

The Izin glossary roles do not automatically imply PKP authority.
No new ADR is proposed: using Laravel, Blade, policies, services, and
the installed DomPDF package follows the existing application structure.

### 3. Requirements, Constraints & Guidelines

- ID: REQ-001; Requirement: Display the authenticated Pegawai's PKP records by
  year and month.

- ID: REQ-002; Requirement: Support structured creation and externally
  prepared document upload.

- ID: REQ-003; Requirement: Allow a variable number of indicators and
  activities per month.

- ID: REQ-004; Requirement: Capture the workbook identity, target,
  realization, and notes fields.

- ID: REQ-005; Requirement: Submit a revision to exactly one assigned Pejabat
  Penilai.

- ID: REQ-006; Requirement: Let that Pejabat Penilai assess, approve, or
  return it with a reason.

- ID: REQ-007; Requirement: Generate an approved application-source PDF
  with the approved revision's data. External records print their source PDF.

- ID: REQ-008; Requirement: Attach a signed document to the exact approved
  revision.

- ID: REQ-009; Requirement: Distinguish approval from signed-document
  completeness.

- ID: REQ-010; Requirement: Preserve prior revisions, decisions, and document
  metadata.

- **REQ-011:** Create corrections without losing the official approved
  revision; switch official content only when a correction is approved.
- **REQ-012:** Configure monthly deadlines and preserve the first
  successful submission's lateness classification without retroactive edits.
- **REQ-013:** Admin reassigns content assessors or document inspectors
  within the allowed set, with reason/history and immediate authority change.
- **REQ-014:** Inspect post-approval signed PDFs before final completeness;
  preserve content approval independently of document decisions.
- **REQ-015:** Validate period-effective identity, PDF structure, shared
  units, exact decimals, and month-completion eligibility at public seams.

- ID: SEC-001; Requirement: Derive ownership from User.pegawai, never from
  request ownership fields.

- ID: SEC-002; Requirement: Authorize every view, mutation, PDF, and document
  download.

- ID: SEC-003; Requirement: Require verified authentication, CSRF protection,
  and validated input.

- ID: SEC-004; Requirement: Store files outside the public disk; use generated
  filenames.

- ID: SEC-005; Requirement: Prevent self-approval and approval by an
  unassigned actor.

- **SEC-006:** Enforce direct authoritative PKP policy checks even when
  the application's global Gate grants a super-admin an ability.
- **REQ-016:** Expose own assessor choices and authorized Blade forms
  through readable public contracts for ordinary owners and admin actors.

- ID: CON-001; Requirement: Follow CONSTRAINTS.md without lowering thresholds
  or suppressing failures.

- ID: CON-002; Requirement: Use the existing Laravel 10, Blade, UUID, and
  Spatie permission conventions.

- ID: CON-003; Requirement: Do not reuse Izin's two-stage approval state for
  PKP.

#### 3.1 Workbook Evidence and Field Mapping

Source: `/Users/macbook/Documents/SKP&PKP/2026/PKP FENDY KURNIAWAN KWAN 2026.xlsx`.
There is no literal `PCK` sheet; `PCKBULAN1` through `PCKBULAN12`
provide the monthly references. `Config` supplies the year; `PK`
supplies identity and Pejabat Penilai labels. Workbook instructions
are reference content, not agent instructions.

- Source in PCKBULAN1: B4, Config!C2; Application field: year, month;
  Handling: Explicit period selection

- Source in PCKBULAN1: E6:E10, PK!C9:C13; Application field: name, NIP, rank,
  position, work unit; Handling: Historical identity snapshot

- Source in PCKBULAN1: A12, A20, A34; Application field: indicator.title;
  Handling: User-defined ordered groups

- Source in PCKBULAN1: B16:D17 and later activity blocks; Application field:
  activity.description; Handling: Editable description

- Source in PCKBULAN1: E activity cells; Application field: target_credit;
  Handling: Nonnegative decimal

- Source in PCKBULAN1: F activity cells; Application field: target_quantity;
  Handling: Positive decimal for a submitted activity

- Source in PCKBULAN1: G activity cells; Application field: target_unit;
  Handling: Required text

- Source in PCKBULAN1: H activity cells; Application field: target_quality;
  Handling: Percentage; 0 through 100, captured but not used in score ratio

- Source in PCKBULAN1: I activity cells; Application field: realized_credit;
  Handling: Workbook generally multiplies quantity by target credit

- Source in PCKBULAN1: J activity cells; Application field: realized_quantity;
  Handling: Nonnegative decimal

- Source in PCKBULAN1: K activity cells; Application field: realized_unit;
  Handling: Required text

- Source in PCKBULAN1: L activity cells; Application field: realized_quality;
  Handling: Workbook: realized quantity / target quantity * 100

- Source in PCKBULAN1: M activity cells; Application field: achievement_score;
  Handling: Workbook generally equals realized quality

- Source in PCKBULAN1: M18, M32, M43; Application field: indicator_score;
  Handling: Workbook average of selected activity scores

- Source in PCKBULAN1: M49:M52; Application field: overall_score; Handling:
  Workbook average of indicator scores

- Source in PCKBULAN1: M53; Application field: predicate; Handling: Workbook
  threshold classification

- Source in PCKBULAN1: A54 and blank note space; Application field: notes;
  Handling: Editable notes

- Source in PCKBULAN1: L56, L60:L61, PK!I32:I33/I37; Application field:
  assessor identity; Handling: Title, name, and NIP in signature block

Do not hard-code the example employee, job-specific activities, or
2025 references in activity descriptions as universal defaults.
Rank and position are not direct fields of Pegawai. Select the latest
non-deleted RiwayatPangkat.tmt and RiwayatJabatan.tanggal_mulai not after
the assessed month's end. Rank comes from pangkat_golongan, work unit
from satuan_kerja, and position from Jabatan.nama through jabatan_uuid.
Missing dates/values or conflicting rows at the latest effective date
produce field validation errors; do not guess or select by UUID. Identical
duplicates may yield the same value snapshot with all source UUIDs retained.
Pegawai.nama and nip use current canonical values at submission, together
with the period-effective fields and source IDs. At approval, compare
the resolved values again; changed applicable values return 409
identity_changed until the owner corrects and resubmits. Record assessor
name/NIP in the approved snapshot; record document inspector separately.
Approved snapshots never refresh from live history. This applies to both
source paths for identity validation; external PDFs are not rewritten.

#### 3.2 Scoring Evidence and Confirmed Aggregation

The workbook computes `realized_quantity / target_quantity * 100`.
For example, target 6 and realization 4 yield 66.666...; target 5 and
realization 3 yield 60. Target quality is displayed but is not used
in these achievement formulas. Values exceeding 100 are possible.

The user selected a cap of 100 for the application. For positive target
quantity and nonnegative realized quantity, use
`activity_score = min(100, realized_quantity / target_quantity * 100)`.
Use this bounded value as the application-generated realized quality
and achievement score. Do not cap the realized quantity or realized
credit. Realized credit remains `realized_quantity * target_credit`.
This is a confirmed deviation from the workbook's uncapped formula.
Indicator and overall scores consequently also remain within 0..100.

PCKBULAN1!M18 averages M17 only, omitting M16; PCKBULAN2!M18
averages M16:M17. Similar omissions occur in other months. This is
a source inconsistency. The user selected aggregation over all activities,
so the application must not reproduce these omissions.

For indicator i with n activities, calculate
`indicator_score[i] = sum(activity_scores[i]) / n`.
For k indicators, calculate
`overall_score = sum(indicator_scores) / k`.
For application-source PKP, reject submission with zero indicators or an
indicator with zero activities. External-source PKP has no activity input.
Drafts may remain incomplete and show no complete overall assessment.
Do not substitute a flat average of all activities.

The observed predicate thresholds are: at most 50 Buruk; at most 60
Sedang; at most 75 Cukup; at most 90.99 Baik; otherwise Sangat Baik.
The user selected no rounding and classification from raw values.
Represent calculations as exact rational numbers derived from validated
decimal inputs, including both averaging stages. Compare thresholds
exactly rather than using binary floating-point or rounded decimal scores.
Store numerator and denominator as decimal integer strings in the
score snapshot, alongside the input snapshot and calculation version;
do not persist computed scores in a fixed-scale decimal column.

For a terminating result, show its exact decimal value without rounding.
For a repeating result, show a decimal preview truncated after six
places with an ellipsis and expose its exact fraction in details.
For example, show `66,666666...`, exact value `200/3`, never `66,67`.
The fraction is authoritative; the preview never drives classification.
The user confirmed this presentation during refinement.
Use the same representation on screen and PDF. A repeating decimal cannot
be rendered with all digits, so its exact fraction must remain available.
Use Brick\Math\BigRational from brick/math 0.12.3, present in composer.lock.
Declare it explicitly in composer.json during implementation if imported
directly. Its rational operations, simplification, and integer components
support the exact calculator contract. No local vendor directory is
available in this checkout; verification uses the tagged upstream source.
Determine whether a normalized fraction terminates by removing factors
2 and 5 from its denominator. If any factor remains, emit only the first
six long-division digits and an ellipsis; do not wait to enumerate a full
repeating cycle. Otherwise emit all digits through zero remainder.
Never round intermediate results or decimals. Bound inputs/counts as below
so exact representations remain computable with arbitrary-size integers.
Machine values use decimal point strings; Indonesian display uses comma.
Manual assessment overrides are prohibited by the confirmed Q1 decision.

### 4. Interfaces & Data Contracts

#### 4.1 HTTP Envelope and Authorization

All PKP routes require authentication, verification, User.status == 1,
non-deleted User, and CSRF for web mutations. A non-deleted linked Pegawai
is additionally required for ownership, creation/submission, assessor or
inspector assignment/decisions, the own-record list, and own assessor
options. Administrative configuration, authorised all-record viewing,
and administrative reassignment require the specified role/permission,
not the acting admin's Pegawai link. A bootstrap super-admin without
Pegawai may configure other employees but may not create a personal PKP
or decide as an unassigned assessor. Resolve nested
revision/document/attempt UUIDs within the specified PKP: mismatch is 404.
User.pegawai determines ownership; reject submitted ownership/actor/path,
score, state, or snapshot fields. Admin role alone never grants content
approval, document inspection, or score override. Self-assignment is invalid.

JSON success: create/upload 201; read/update/transition 200. Web success:
303 redirect to the affected PKP or configuration view. Responses include
`data`, `version`, and where applicable `official_revision_uuid`,
`pending_revision_uuid`, `completeness`, and `lateness`. Version is the
PKP aggregate version; every successful mutation increments it once.
Configuration records have their own versions. Failed requests do not.

Errors have `error.code`, `error.message`, and `error.fields` (when needed).
JSON field validation is 422; web validation redirects with field errors
and preserved non-file inputs. Authorization is 403, missing/nested mismatch
404, stale version or state conflict 409, storage/database failure 503.
No private paths, raw parser output, or document contents enter user errors.
All authorized existing-record mutations require integer expected_version.
Missing version is 422; stale version is 409 stale_version.

Permissions seeded for admin/super-admin: `pkp.view-all`,
`pkp.manage-assessors`, `pkp.manage-deadlines`, and `pkp.reassign`.
Ownership and actual assignment are policy predicates, not Izin role names.
Admin configuration actions require both admin/super-admin role and the
corresponding permission. Assigned assessors may read submitted attempts
and their decision history; owners and view-all actors may read full
records. Assigned document inspectors may read the approved snapshot and
its documents. View authorization also governs print/private downloads.
Only owners save drafts, submit, create corrections, or upload documents.

##### Authoritative PKP Policy Integration (A-001 / A-002)

The current AuthServiceProvider::boot registers Gate::before returning
true for super-admin. A Gate permission check can therefore return true
before a model policy runs. Keep that global behavior unchanged for
existing modules; do not widen or remove it as part of PKP integration.

Introduce `PkpAuthorization` as the public module authorization boundary.
It directly invokes real PkpPolicy predicates, not Gate::allows/authorize,
User::can, or a role-based unconditional allow callback. The same boundary
is invoked by HTTP adapters/read queries and again inside mutation services
under locks. PKP views receive capability booleans computed by it; never
use an unqualified Blade @can or a positive generic Gate result to expose
PKP actions or records. Register/discover PkpPolicy normally, but a generic
Gate result is not the authoritative PKP decision.

Administrative predicates directly require hasRole(admin|super-admin)
plus hasPermissionTo(the specific seeded pkp permission), together with
account eligibility. Absent permissions deny access. These direct checks
do not recurse through User::can. Owners/assessors require real Pegawai
identity and current assignment; no PKP approval/inspection bypass is
granted by super-admin role or a permission with an assignment-like name.
Both content decision and document decision paths must enforce this.

Public contract: `PkpAuthorization.allows(User actor, string action,
PkpContext context): bool` and `authorize(...): void`, throwing a forbidden
domain exception on false. Context contains the loaded PKP/revision,
candidate/submission when applicable, and target Pegawai for configuration;
no actor or assignment is accepted from client payload. Actions are
viewOwnList, viewAll, view, create, save, revise, submit, refreshIdentity,
contentDecision, documentUpload, documentDecision, print, download,
manageAssessorSettings, manageDeadlines, reassignContent, reassignInspector,
and viewOwnAssessorOptions. PkpPolicy's corresponding predicates must
resolve the same table/state conditions defined by these routes.

HTTP regression fixtures must load the actual AuthServiceProvider and
Spatie permissions. A linked super-admin can have a positive generic
Gate result while an unassigned PKP decision is still 403 through the
authoritative module boundary. Test that combination without stubbing
Gate, policy, actor relationships, or the domain service. Separately test
admin/super-admin without Pegawai for administrative actions and personal
action denials. Existing Izin/Cuti and role-management permission behavior
must remain unchanged; no bypass-removal refactor is included.

#### 4.2 Route Inventory and Exact Payloads

Register fixed `/pkp/assessments` and configuration routes before UUID
routes. UUID route constraints and nested membership are mandatory.

- **GET /pkp:** `year` integer 1900..9999, `scope=own|all` (default own),
  page >= 1. Scope all requires view-all. Paginate 25 records by descending
  year/month, then UUID. Include official/pending references and both
  content approval and document completeness; missing PKP is distinct
  from an existing incomplete PKP.
- **GET /pkp/assessments:** `kind=content|document` (default content),
  same pagination. Show only this actor's assigned outstanding work;
  no global queue implied by an assessor's Izin role.
- **GET /pkp/my-assessor-options:** Owner-authenticated own scope, no
  employee UUID parameter. Return `data.settings_version` (0 when unset),
  `data.configured_default_uuid` (nullable), `data.default_assessor_uuid`
  (nullable eligible default), and `data.options`, containing only eligible
  allowed alternatives as `{pegawai_uuid, name, nip}`. Empty/unset settings
  return 200 with null default and empty options; owner may save a draft
  but not submit without a valid selection. Non-owner employee parameters
  are rejected; this endpoint never exposes another employee's settings.
  Save and submit revalidate membership/eligibility even if this read
  previously returned an option. It grants no configuration-write access.
- **GET /pkp/create:** Eligible owner only; render the Blade creation form
  with month/year, source choices, own assessor options/default, and CSRF.
  The default period is the current local Asia/Makassar month. Also expose
  the form-data payload to JSON clients without creating records on GET.
- **GET /pkp/{uuid}/revisions/{revision}/edit:** Owner-only bound pending
  draft/returned revision; render editable fields, expected PKP version,
  own assessor options, and validation feedback. Submitted/approved edit
  returns 409 revision_not_editable; a mismatched UUID is 404.
- **GET /pkp/assessor-settings/{pegawai_uuid}:** Admin/manage-assessors;
  return configured default, allowed set, and version (0 if absent).
  HTML renders the corresponding admin settings form with actual version.
- **GET /pkp/deadlines/{year}/{month}:** Admin/manage-deadlines;
  return deadline_local, UTC instant, and version (0 if absent).
  HTML renders the admin deadline form with actual version and timezone.
- **POST /pkp:** `{year, month, source: application|external,
  assessor_pegawai_uuid?: UUID}`. Create PKP/version 1, draft revision 1,
  pending binding, and period deadline sentinel atomically. Default assessor
  is preselected if eligible; missing settings allow draft but not submit.
  Duplicate Pegawai/year/month returns 409 duplicate_period with existing
  PKP UUID/authorized detail link. Future draft creation is permitted.
  Create an unset owner-assessor-settings sentinel if absent so settings
  and submission always lock a real row. Unset sentinels use version 0;
  first admin configuration uses expected_version 0 and increments to 1.
- **GET /pkp/{uuid}:** Return monthly metadata, official-approved snapshot,
  pending state, first-submission classification, and authorized history.
  Never derive the official version from the latest creation timestamp.
  The Blade detail page exposes the authorized submit/correction/refresh,
  assessor decision, print, upload, and document decision actions with
  capability booleans from PkpAuthorization. JSON clients receive those
  booleans and existing response metadata; no hidden UI-only authorization.
- **PUT /pkp/{uuid}/revisions/{revision}:**
  `{expected_version, assessor_pegawai_uuid?, notes?, indicators?}`.
  Owner may save only the bound pending draft/returned revision. Replace
  the submitted indicator tree atomically in its supplied order; omitted
  indicators preserve existing rows, an explicit empty array clears them.
  External source rejects indicators. Source and period cannot change
  on this endpoint; choose another source in a correction revision.
- **POST /pkp/{uuid}/revisions:**
  `{expected_version, base_revision_uuid, source: application|external}`.
  Owner-only; base must be current official approved revision and no
  pending revision may exist. Allocate next revision number under lock.
  Same-source application corrections copy editable approved inputs;
  external corrections need a new PDF upload. Changed-source corrections
  start empty. Never copy approval, accepted document bindings, or scores
  into a draft. Return 409 pending_revision_exists or base_revision_changed
  if preconditions fail; exactly one racing create can succeed.
- **POST /pkp/{uuid}/submit:** `{expected_version, revision_uuid}`.
  Owner-only bound pending draft/returned revision. Validate eligible
  assessor/allowed membership, period <= current Asia/Makassar month,
  effective identity, and source-specific completeness. Invalid fields
  produce 422; a future period produces 422 period_not_submittable.
  Freeze an immutable submission attempt, assignment, input/file hashes,
  identity, and calculated snapshot (application only). Set submitted.
  On first success only, capture submission time and deadline/lateness.
- **POST /pkp/{uuid}/decision:**
  `{expected_version, revision_uuid, submission_uuid,
  decision: approve|return, reason?: string}`.
  Only the current assigned content assessor may decide the current
  submitted attempt. Return requires a nonblank reason. Approve before
  the next month's first local instant returns 409 period_not_finished.
  Identity changed since submission returns 409 identity_changed;
  leave the submission frozen until its owner uses refresh-identity.
  Approval atomically marks approved, switches official binding, clears
  pending, preserves history, and sets document inspector to that assessor.
  An already-signed external source simultaneously becomes accepted final.
  Return marks returned and preserves that attempt/decision evidence.
- **POST /pkp/{uuid}/refresh-identity:**
  `{expected_version, revision_uuid, reason}`. Owner-only submitted pending
  revision; permit only when applicable identity values have changed.
  Close the attempt as withdrawn_identity_changed, retain its snapshot,
  and set returned. Owner must save and submit again with resolved identity.
  Unchanged identity returns 409 identity_unchanged. This is not a general
  withdrawal or reassignment bypass; first-submission history is preserved.
- **POST /pkp/{uuid}/reassign-assessor:**
  `{expected_version, revision_uuid, assessor_pegawai_uuid, reason}`.
  Admin with reassign authority changes only a bound submitted/returned
  unapproved revision. New assessor must be eligible and allowed. Log
  previous/new assignments and reason; old authority ends on commit.
  A submitted reassignment binds the same immutable content attempt;
  record assignment separately rather than rewrite its historical snapshot.
  At approval, snapshot the current assigned assessor and retain the old
  attempt's original assessor snapshot in history. The original attempted
  assignment must not be mistaken for the current decision authority.
- **POST /pkp/{uuid}/reassign-inspector:**
  `{expected_version, revision_uuid, inspector_pegawai_uuid, reason}`.
  Admin with reassign authority; current official approved revision only.
  Reassign only if original/current inspector is unavailable (ineligible
  or admin-attested absence with reason). New inspector is eligible,
  allowed, and not owner. Preserve the original content decision actor.
- **POST /pkp/{uuid}/revisions/{revision}/documents:** Multipart fields
  `expected_version`, `kind=source|signed_final`, `file`, and source-only
  `declared_signed` boolean default false. A signed_final replacement when
  an accepted final exists additionally requires `content_unchanged=true`.
  Encode multipart booleans as 1/0; true in this contract is the validated
  logical value, not an unvalidated arbitrary string.
  Source uploads: owner, external
  pending draft/returned only. Final uploads: owner, current official
  approved revision only; application, unsigned external, and already-signed
  external paths are allowed under the source matrix below.
  An already accepted final may have one pending replacement. Upload
  creates immutable metadata and changes the corresponding candidate
  binding, not content approval or the previously accepted final binding.
- **POST /pkp/{uuid}/documents/{document}/decision:**
  `{expected_version, revision_uuid, decision: accept|return, reason?,
  matches_approved_content?: boolean}`. Acceptance requires an explicit
  `matches_approved_content=true` from the assigned inspector.
  Assigned current document inspector only; document must be the bound
  pending signed_final candidate of the current official revision. Return
  requires reason and sets returned; acceptance binds accepted final and
  clears candidate atomically. Approvals/scores/identity remain unchanged.
  Replaced, archived, stale, or already-decided candidates return 409.
- **GET /pkp/{uuid}/revisions/{revision}/pdf:** View-authorized approved
  revision only, otherwise 409 revision_not_approved. Application source
  renders approved data as A4 landscape. External source streams exact
  approved source PDF. Both use private, no-store caching. No draft print.
- **GET /pkp/{uuid}/documents/{document}/download:** View-authorized,
  revision-bound private PDF attachment; missing object returns 404.
  No fallback to public disk or a user-supplied path.
- **PUT /pkp/assessor-settings/{pegawai_uuid}:**
  `{expected_version, default_assessor_uuid, allowed_assessor_uuids}`.
  Admin/manage-assessors only; valid nonempty eligible allowed set, no
  duplicates/self, default a member. Version 0 creates absent settings.
  Removing an assessor from allowed settings does not retroactively cancel
  a frozen assignment, but disables their selection for new submissions.
  Changing default never silently reassigns submitted/approved content.
- **PUT /pkp/deadlines/{year}/{month}:**
  `{expected_version, deadline_local: string|null}`. Admin/manage-deadlines
  only; local format YYYY-MM-DDTHH:mm:ss interpreted in Asia/Makassar.
  Reject invalid calendar dates/offset-bearing or fractional timestamps.
  Store UTC microsecond-capable timestamp (configured deadlines use zero
  microseconds). Version 0 creates missing period configuration; null
  removes the deadline for not-yet-submitted owners only. Audit all edits.
  One year/month row applies to all Pegawai, independent of source path.

##### Signed-Final Source Matrix (D-SPEC-01)

This matrix applies only to the current official approved revision and
its owner. Other actor/state checks remain mandatory; there is no admin
or legacy signed-source exception to inspection of a new final candidate.

| Source | Original declared signed | First accepted final | Later file |
| --- | --- | --- | --- |
| application | Not applicable | Upload + inspection | Replacement + review |
| external | false | Upload + inspection | Replacement + review |
| external | true | Reuse reviewed source on approval | Replacement + review |

Original signed-source reuse remains the Q7 shortcut: no second upload
or second document inspection is needed for the exact source already
reviewed in the content approval. Every newly uploaded replacement is a
different immutable document and requires post-approval inspection.
This source rule resolves P-001; the planner must align its gate and ACs.

Document-only replacement changes the quality/completeness of the scan,
not the approved PKP content. Owner declaration is not proof of equivalence.
The inspector compares the candidate with the approved submission and,
for external PKP, the frozen approved source PDF. No OCR or automated
semantic comparison is introduced. Inspector cannot accept a candidate
that changes period, employee, target, realization, or assessment content.
Return it with a reason explaining the need for a new content revision.

Missing/false owner `content_unchanged` for replacement is 422
content_revision_required with field error and no new binding. Missing/false
`matches_approved_content` on acceptance is 422 content_not_confirmed;
candidate remains pending and no review decision is committed. To reject
actual changed content, the inspector explicitly returns the candidate
with a nonblank reason; the original accepted final remains current.
Failure messages direct the owner to the existing correction workflow.
Source uploads, first final uploads, and return decisions do not require
these replacement-only/acceptance-only declarations respectively.

Validation, staging failures, stale versions, revoked inspector authority,
or candidate return leave accepted A intact. Acceptance of B changes only
the current final binding; approved source A, submission snapshot, content
decision actor/score, prior file/review, and initial lateness remain intact.
External `/pdf` still streams the original approved source; the detail page
and document-download action identify the current accepted final separately.
Archived revisions cannot accept replacements through this endpoint.

#### 4.3 Input and Calculator Contracts

Creation, editable detail, review, and configuration are Blade pages,
not API-only flows. PKP navigation is available to eligible linked owners
including ordinary user role, and to authorised administrative accounts.
It must not inherit the existing admin/atasan-only navigation grouping.
Admin without Pegawai navigates to `/pkp?scope=all` or configuration pages;
personal creation remains forbidden. Both desktop and mobile navigation
use module-authorized capability booleans, not generic @can checks.
Assessor choices are populated from the own-options contract; inaccessible
admin settings must not be fetched by an owner's form. Show an empty
configuration message when options are absent, allow draft persistence,
and explain why submission requires an allowed eligible selection.

Use existing Alpine/axios support to save the indicator tree as JSON,
including CSRF, expected version, and server-returned field errors. Do not
submit thousands of flattened form variables for the 500-activity bound:
PHP max_input_vars must not silently truncate the tree. Files use their
separate multipart upload endpoint. Re-read metadata after 409 and require
review of the current state before retrying, never silently overwrite.

Application activity JSON has `description`, `target_credit`,
`target_quantity`, `unit`, `target_quality`, and `realized_quantity`.
The single unit renders in both target and realization workbook columns.
Reject legacy unequal target_unit/realized_unit; new clients send only unit.
Year 1900..9999 and month 1..12 are integers, not inferred from filename.
At most 50 indicators and 500 total activities. Title <= 500 characters,
description <= 2,000, unit <= 100, notes/reason <= 5,000. Required texts
must be nonblank after trimming. Strings are escaped in Blade/PDF output.

All non-null numeric inputs are unsigned invariant decimal strings,
no exponent notation, commas, signs, NaN, infinity, or leading/trailing
whitespace. Quantity fits decimal(14,4), <= 9999999999.9999; credit fits
decimal(14,6), <= 99999999.999999; target quality <= 100 with <= 4 decimals.
Reject excess scale even if database rounding would fit. Draft fields can
be null; submit requires complete fields and target_quantity > 0.
Realized quantity/credit may equal zero. Units must already be shared.
Computed scores/realized quality/credit are server-only and never editable.
Decimal grammar is `^(0|[1-9][0-9]*)(\.[0-9]+)?$`; scale/range checks follow.
Reject leading zero variants, unknown keys, and malformed UUIDs. Draft
nulls are permitted only for the listed incomplete fields, not ownership,
source, period, or supplied aggregate version.

For external PKP, source PDF, eligible assessor, period, and identity are
required on submit; indicators and score fields are prohibited. Calculation
version and score snapshot are null; display unavailable, never zero.
No statistics dashboard or OCR is introduced.

The following declarations are specification contracts, not source files.
UUIDs are strings, decimals are strings, time instants are UTC
DateTimeImmutable. Expected PKP aggregate versions are integers >= 1.
Configuration expected versions are integers >= 0: zero applies only to
absent/unset sentinel version 0, first configure produces version 1, and
subsequent configure must match the actual nonzero version. This exception
does not permit expected_version 0 for record/revision/document mutations.

```php
interface PkpCalculator
{
    /** @param list<IndicatorInput> $indicators */
    public function calculate(array $indicators): CalculationResult;
}

interface PkpPdfValidator
{
    public function inspect(string $privateStagedPath): PdfInspection;
}
```

- **IndicatorInput:** title string; activities list of ActivityInput.
- **ActivityInput:** description/unit string; targetCredit, targetQuantity,
  targetQuality, realizedQuantity validated decimal strings.
- **ExactValue:** numerator signed integer string; denominator positive
  integer string; normalized by GCD; display string; repeating boolean.
- **CalculationResult:** version `pkp-v1`; ordered indicator/activity
  result trees; overall ExactValue; predicate string. Each activity
  result includes realizedCredit, realizedQuality, achievement ExactValue.
  Indicator score equally averages its activity results; overall equally
  averages indicators. Empty/incomplete input throws validation errors.
- **PdfInspection:** sha256 lowercase 64-character hex; byteSize integer;
  pageCount >= 1; detectedMime application/pdf; validatorVersion string.
  No invalid result object: errors are typed InvalidPdf or unavailable.
  The public typed output is `App\Support\PdfInspection`, allocated to
  `app/Support/PdfInspection.php`. All five result fields are read-only
  values after construction. Input inspection errors return no instance.
  This is a source contract to schedule in the plan, not a file created here.

Public service seams use these precise method argument/result contracts:

- `PkpAssessorOptionsService.forOwner(User actor)` returns
  AssessorOptionsResult with settingsVersion, configuredDefaultUuid?,
  eligibleDefaultUuid?, and eligibleOptions list of UUID/name/NIP. Derive
  own Pegawai identity from actor, using the authoritative policy boundary.

- `PkpSubmissionService.create(actor, year, month, source, assessor?)`
  returns PkpMutationResult with record/revision UUID and version 1.
- `save(actor, pkpUuid, revisionUuid, expectedVersion, DraftInput)` returns
  PkpMutationResult; `revise(actor, pkpUuid, expectedVersion, baseUuid,
  source)` returns new pending revision UUID and incremented version.
- `submit(actor, pkpUuid, revisionUuid, expectedVersion, serverNow)` returns
  PkpMutationResult including immutable submission UUID and lateness.
- `refreshIdentity(actor, pkpUuid, revisionUuid, expectedVersion, reason)`
  returns PkpMutationResult and closed-attempt identity-change evidence.
- `PkpAssessmentService.decide(actor, pkpUuid, revisionUuid, attemptUuid,
  expectedVersion, decision, reason?, serverNow)` returns PkpMutationResult.
- `PkpAssignmentService.reassignContent/reassignInspector(actor, pkpUuid,
  revisionUuid, expectedVersion, newPegawaiUuid, reason)` returns same.
- `PkpDeadlineService.configure(actor, year, month, expectedVersion,
  deadlineLocal?)` returns ConfigurationResult with UTC value/version.
- `PkpDocumentService.store(actor, pkpUuid, revisionUuid, expectedVersion,
  kind, UploadedFile, declaredSigned, contentUnchanged?)` returns document
  UUID and mutation
  result; `decide(actor, pkpUuid, documentUuid, revisionUuid,
  expectedVersion, decision, reason?, matchesApprovedContent?)` returns
  PkpMutationResult. Optional declarations become mandatory under the
  route/source/accepted-final conditions specified above; no client-supplied
  actor or base-document binding is accepted.
- `PkpDocumentService.download(actor, pkpUuid, documentUuid)` returns a
  StreamedResponse; `PkpPdfService.render(actor, pkpUuid, revisionUuid)`
  returns an application PDF Response or external StreamedResponse.
- `PkpAssessorSettingsService.configure(actor, pegawaiUuid,
  expectedVersion, defaultUuid, allowedUuids)` returns ConfigurationResult.

Actor is App\Models\User. DraftInput is nullable notes and optional ordered
indicator tree/assessor; source and owner are absent. PkpMutationResult is
recordUuid, version, officialRevisionUuid?, pendingRevisionUuid?,
submissionUuid?, documentUuid?, completeness, lateness. ConfigurationResult
is configuration UUID, version, and validated configured values.
HTTP adapters own status/redirect conversion; services enforce locked
policy checks and throw validation, authorization, not-found, conflict,
or infrastructure exceptions. serverNow comes from an injected application
clock at the locked transition, never a request-supplied timestamp.

#### 4.4 Logical Storage Schema

Apply additive migrations compatible with PostgreSQL 16 and MySQL 8.
No migration is executed in this phase. UUID columns use native UUID
where supported or Laravel's compatible representation; preserve FK types.
Checks are enforced through migrations on supported engines and through
validation; do not rely on SQLite locks for production guarantees.

- **pkp:** uuid PK; pegawai_uuid FK RESTRICT; year smallint; month 1..12;
  unique(pegawai_uuid, year, month); version integer; official_revision_uuid
  nullable; pending_revision_uuid nullable; first_submitted_at nullable UTC;
  deadline_snapshot_at nullable UTC; lateness nullable enum
  on_time/late/deadline_not_set; timestamps. Null lateness means never
  submitted, distinct from submitted without a deadline.
- **pkp_deadlines:** uuid PK; year/month unique; deadline_at nullable UTC;
  version integer; timestamps. Create an unset sentinel transactionally
  when first creating PKP, or admin configures a previously absent month.
  Unique upsert/retry prevents two sentinels under concurrent first create.
- **pkp_assessor_settings:** uuid PK; pegawai_uuid unique FK; nullable
  default_assessor_uuid FK; version integer; timestamps. Missing settings
  permit draft persistence but not submission.
- **pkp_allowed_assessors:** settings_uuid/assessor_pegawai_uuid FKs;
  unique(settings_uuid, assessor_pegawai_uuid). Configuration audit uses
  pkp_configuration_events, never deletes historical assignment evidence.
- **pkp_configuration_events:** uuid PK; kind deadline/assessor_settings;
  configuration_uuid; actor_user_uuid FK; before_json/after_json; UTC time.
- **pkp_revisions:** uuid PK; pkp_uuid FK; number integer unique per PKP;
  source application/external; state draft/submitted/returned/approved;
  assessor_pegawai_uuid nullable FK; inspector_pegawai_uuid nullable FK;
  notes nullable text; current_submission_uuid nullable;
  approved_submission_uuid nullable; approved_assessor_snapshot nullable JSON;
  source_document_uuid nullable; final_document_uuid nullable;
  pending_final_document_uuid nullable; approved_at nullable UTC; timestamps.
- **pkp_indicators:** uuid PK; revision_uuid FK; title nullable text;
  position integer unique per revision. Stored draft order is contiguous.
- **pkp_activities:** uuid PK; indicator_uuid FK; position unique per
  indicator; nullable description, unit varchar(100), target_credit
  decimal(14,6), target_quantity/realized_quantity decimal(14,4),
  target_quality decimal(10,4). Nullability supports incomplete drafts.
  Submit snapshots are complete and immutable.
- **pkp_submissions:** uuid PK; revision_uuid FK; attempt number unique
  per revision; state submitted/returned/approved/withdrawn_identity_changed;
  input_snapshot JSON; identity_snapshot JSON; assessor_snapshot JSON;
  source_document_uuid/hash nullable; calculation_version nullable;
  score_snapshot JSON nullable; payload_sha256 char(64); identity_sha256
  char(64); submitted_at UTC. Immutable payload snapshots;
  attempt state/decision linkage changes only through authorized transitions.
  Immutable input snapshots include selected activity IDs/order and values,
  employee history IDs/values, and content hash; attempts remain downloadable
  as authorized historical detail after edits. Never serve live draft rows
  as an older submission's evidence.
- **pkp_decisions:** uuid PK; submission_uuid unique FK; actor_user_uuid FK;
  decision approve/return; nullable reason; decided_at UTC. One content
  decision per submitted attempt. Identity withdrawal is an audit event,
  not an assessor decision or a score override.
- **pkp_documents:** uuid PK; revision_uuid FK; kind source/signed_final;
  local private path; original_name; detected_mime; byte_size;
  sha256; page_count; validator_version; declared_signed boolean;
  uploaded_by_user_uuid FK; uploaded_at UTC;
  replacement_of_document_uuid nullable FK; content_unchanged_declared
  nullable boolean. The replacement base is captured by the server from
  the currently accepted final under the same aggregate lock, never from
  request payload. Metadata/content immutable; retain archived base evidence.
- **pkp_document_reviews:** uuid PK; document_uuid unique FK;
  actor_user_uuid FK; decision accept/return; reason nullable;
  basis post_approval_inspection/content_approval_reuse; decided_at UTC.
  Capture approved_submission_uuid and replacement-base UUID as review
  evidence. Post-approval accept records matches_approved_content=true;
  source-reuse acceptance records its content-decision basis rather than
  inventing a separate post-approval inspection declaration.
- **pkp_events:** uuid PK; pkp_uuid/revision_uuid nullable FKs as appropriate;
  actor_user_uuid FK; action; before/after data JSON; reason nullable;
  UTC time. Include assignment changes and previous/current actor IDs.

Official/pending revisions must belong to that PKP; source/final documents
and submissions must belong to their referenced revision. Add nullable
circular-reference FKs after tables exist; RESTRICT referenced evidence.
Where composite membership FKs are impractical, enforce inside locked
services and test cross-PKP UUID attempts. Do not cascade delete audit
records or soft-deleted employee evidence. No public delete/purge route.
Snapshot score values contain integer-string numerator/denominator pairs,
not fixed-scale score decimals. Archived files remain privately retained.

#### 4.5 States, Time, and Concurrency

One bound pending revision may be draft, returned, or submitted. Saving a
returned revision never overwrites prior attempt snapshots. Official
revision is always approved. During a correction, continue serving the
old official revision/completeness alongside the pending revision. Approval
atomically switches official and clears pending. If the new revision
has no accepted final, show awaiting_signed_upload regardless of old files.

Completeness for current official revision is complete iff accepted
final_document_uuid is bound; otherwise pending candidate means
awaiting_document_review, no candidate means awaiting_signed_upload.
No official revision means not_approved. A rejected candidate is shown
as document_returned with reason, unless an older accepted final within
that same revision remains current (complete with replacement returned).

External declared-signed source approval reuses the exact reviewed file,
creates content_approval_reuse acceptance evidence, and completes final
binding without upload/copy or a second inspection. Unsigned external
approval waits for post-approval signed upload/inspection, as application
source does. New inspector assignment affects only document decisions.
Already-signed external revisions may also receive document-only final
replacement candidates, using the same inspection/acceptance transitions.
The reused source stays the accepted final until that new candidate is
accepted, then remains an immutable archived source and former final.

Submit allowed through current local month with no past cutoff in valid
year range. Approve only when server clock >= first instant of next month
in Asia/Makassar. Review/return may occur earlier. Submission instants use
UTC microsecond precision; deadlines use configured local second precision.
At the exact deadline instant, on_time; a microsecond later, late.

Serialize configuration and mutations with a consistent lock order:
period-deadline sentinel, owner assessor settings when present, PKP parent,
revision/current attempt, then document/candidate. Config-only actions lock
only their corresponding configuration row. Recheck actor eligibility and
assignment inside every transition. Unavailable new configuration cannot
rewrite approved assignments; reassignment logs before/after actors.
On concurrent reassignment and approval, the lock winner acts first:
if approval wins, later content reassignment returns 409 revision_approved;
if reassignment wins, the old assessor decision returns 403. Stale versions
otherwise return 409 before any mutation. Authorization checks precede
version disclosure for a revoked actor. Duplicate decisions cannot overwrite.
Initial sentinel creation is unique-upserted before taking the ordered
locks; duplicate-key races reload the winner without resetting its version.
Transient deadlock/serialization failures roll back and may retry the whole
transaction at most twice after the original attempt. Revalidate expected
version and authority on every attempt. Exhaustion returns 503 with no
partial effects. A successfully committed request is never retried blindly;
clients refresh the returned conflict/version before repeating mutations.

Approval resolves period-effective identity again inside its transaction.
Values committed before that authoritative read must be included; changes
after the approval snapshot require a subsequent correction, never live
rewriting of approved data. Concurrent history updates must be exercised
on the deployment database so approval reads a consistent identity set.

First accepted submit alone captures server time/deadline/classification
under locks. Validation or infrastructure failures do not initialize it.
Snapshot missing deadlines as null plus deadline_not_set; later settings
cannot classify that record retroactively. Returned edits, new revisions,
and identity refresh preserve the first event/classification.

#### 4.6 PDF Inspection, Private Storage, and Print Layout

Accept only detected application/pdf, 1..10,485,760 bytes, qpdf-validated
structure and page count >= 1, without a required password. Validate on
an unreferenced server-generated staged path under private local storage.
Use an absolute configured qpdf executable (>= 11), process argument arrays,
no shell interpolation or request-derived executable/options. Do not
rewrite the file; its SHA-256 must match what the assessor later reviews.

Inspection uses separate qpdf --check, --requires-password, and
--show-npages operations. --check must return 0; 2/3 rejects structural
errors/warnings. For --requires-password without a supplied password,
0 rejects password-required input; 2 (unencrypted) or 3 (opens without
password) may proceed only after a clean check. --show-npages must return
0 with a positive integer. These distinct exit conventions must not be
collapsed into generic nonzero-failure handling. No warning suppressions.
Qpdf does not prove signature authenticity or semantic visual correctness;
assessor inspection still handles the signed document's actual content.

Delegated process bounds: at most 5 seconds per inspection operation,
15 seconds total, output capped at 64 KiB per operation. Exceeded parsing
bounds yield 422 pdf_validation_limit; unavailable executable/failure to
start yields 503 pdf_validator_unavailable. Retain previous metadata/file.
Never call a failed validator a successful upload. Capture diagnostic
error categories internally without logging sensitive document output.
The Code plan must provide qpdf in supported runtimes and meaningful real
PDF fixtures; this spec does not install qpdf or assert host availability.

After inspection, move bytes to a UUID filename in dokumen/pkp on local
private storage, then bind metadata transactionally against version/state.
A failed metadata commit may clean up only that unreferenced new file;
a failed move leaves old bindings intact. Download sets private/no-store
and attachment headers; no public symbolic-link fallback. No automatic
purge of bound/archived evidence. Orphan cleanup must prove lack of bindings.

Application prints use approved submission snapshots, A4 landscape,
repeated headers, uncut activity text, indicators with their summaries,
overall exact score/predicate, notes, employee identity, and assessor
signature block. Long content flows to additional pages; do not shrink
fonts or omit data to force one page. Exact fraction details appear in a
calculation appendix. External printing streams the unchanged approved
source bytes; signed final download is a separate document action.

### 5. Acceptance Criteria & Testable Behaviors

- ID: AC-001; Given / When / Then: Given a linked Pegawai, creating a month
  produces an owned monthly record.

- ID: AC-002; Given / When / Then: Given that month already exists, a second
  create cannot produce a duplicate.

- ID: AC-003; Given / When / Then: Given another owner's record, unauthorized
  view/edit/download is denied.

- ID: AC-004; Given / When / Then: Given editable content, adding/removing
  ordered indicators and activities persists the full structure.

- ID: AC-005; Given / When / Then: Given an incomplete application record,
  submission returns field errors without changing state.

- ID: AC-006; Given / When / Then: Given a complete draft, submission freezes
  the submitted revision and exposes it to its assigned assessor.

- ID: AC-007; Given / When / Then: Given an unassigned or self actor, approval
  is denied without a decision event.

- ID: AC-008; Given / When / Then: Given a submitted revision, return requires
  a reason and makes it editable by the owner.

- ID: AC-009; Given / When / Then: Given an approved application revision,
  its PDF
  contains approved identity, activities, results, and assessor signature
  block.

- ID: AC-010; Given / When / Then: Given approval, signed upload creates
  pending inspection; acceptance completes the document while content
  approval and scores remain unchanged.

- ID: AC-011; Given / When / Then: Given a signed document, a later correction
  preserves the previous approval and attachment.

- ID: AC-012; Given / When / Then: Given two concurrent decisions, only one
  transition succeeds; the other receives a conflict.

- ID: AC-013; Given / When / Then: Given a disallowed/oversized upload,
  validation rejects it with no retained orphan metadata.

- ID: AC-014; Given / When / Then: Given a storage failure, the operation
  fails visibly and preserves previous document availability.

- ID: AC-015; Given / When / Then: Given a User without a Pegawai, create is
  rejected without an unowned record.

- ID: AC-016; Given / When / Then: Given profile changes after approval, the
  historical PDF retains its approved identity snapshot.

- **AC-017:** Given any actor, including the Pejabat Penilai, a request
  to override a calculated score is rejected without changing the score.
- **AC-018:** Given a submitted revision, approval preserves calculated
  scores; return enables owner corrections and recalculation on resubmit.

- **AC-019:** Given indicator A activities scoring 60 and 100 and
  indicator B with one activity scoring 100, indicator scores are 80
  and 100 and the overall score is 90, not the flat average 86.666....
- **AC-020:** Given two activities in an indicator, both contribute to
  its score; the workbook's first-activity omissions are not reproduced.
- **AC-021:** Given an application-source record with no indicators or an
  empty indicator, submission is
  rejected with validation errors and no approval-ready overall score.

- **AC-022:** Given an external PDF, period, and assigned assessor,
  submission succeeds without activities or a numeric score.
- **AC-023:** Given an external revision without a PDF, submission fails
  validation and retains the draft state.
- **AC-024:** Given an external submission, its assigned assessor can
  approve or return it; upload alone never constitutes approval.
- **AC-025:** Given an approved external revision, download returns its
  frozen source PDF and its score is unavailable.
- **AC-026:** Given an external revision, activity and calculated-score
  fields are rejected; statistics never infer a zero score.
- **AC-027:** Given configured settings, draft creation preselects the
  default assessor and permits an owner-selected allowed alternative.
- **AC-028:** Given an assessor outside the owner's allowed set, save or
  submission rejects the selection without changing assignment.
- **AC-029:** Given an unauthorized actor, assessor configuration is denied;
  admin changes must preserve default membership and reject self-assignment.
- **AC-030:** Given changed configuration, an existing submitted or approved
  revision retains its assessor; an ineligible draft selection cannot submit.
- **AC-031:** Given missing settings or a default outside the allowed set,
  submission/configuration fails validation rather than guessing an assessor.
- **AC-032:** Given target quantity 5 and realized quantity 6, the activity
  score is 100, not 120, while stored realized quantity remains 6.
- **AC-033:** Given one indicator with activities 5/6 and 5/3
  (target/realized), its score is 80: average of capped 100 and 60.
  Do not average uncapped 120 and 60 into an incorrect 90.
- **AC-034:** Given target credit 0.02 and realized quantity 6, realized
  credit is 0.12 even when the achievement score is capped at 100.
- **AC-035:** Given target 3 and realization 2, achievement is exactly
  200/3; aggregation and predicate comparison use that fraction.
- **AC-036:** Given overall score exactly 90.99, predicate is Baik;
  given 90.991, predicate is Sangat Baik and the display preserves 90.991.
- **AC-037:** Given overall scores exactly 50, 60, 75, and 100,
  predicates are Buruk, Sedang, Cukup, and Sangat Baik respectively.
- **AC-038:** Given repeating scores, screen and PDF show the authoritative
  fraction; truncated previews never change stored scores or predicates.
- **AC-039:** Given an external source declared signed, approval binds that
  exact PDF as the initial accepted final without another upload, duplicate
  storage object, or second inspection. Later document-only replacement
  is allowed and requires a fresh post-approval inspection.
- **AC-040:** Given an external source not declared signed, approval leaves
  the revision awaiting a signed-final upload and inspection; upload alone
  never completes it. Subsequent same-content replacements are allowed.
- **AC-041:** Given an unapproved declared-signed source, the list still
  shows pending approval and does not report final completeness.
- **AC-042:** Given a returned external revision and replacement PDF,
  the old declaration and approval cannot complete the replacement.
- **AC-043:** Given an application-source approved revision, signed-final
  upload remains required; external-source reuse does not apply.

Scoring threshold and signed-source reuse rules above are confirmed.
The following criteria cover audit decisions and delegated resolutions.

- **AC-044:** Given approved R1 and no pending correction, revision-create
  returns draft R2 while R1 remains official and its completeness unchanged.
- **AC-045:** Given concurrent correction creates with the same version,
  exactly one pending revision is created; the loser receives 409.
- **AC-046:** Given R2 approval without an accepted signed final, official
  switches to R2 and completeness becomes awaiting_signed_upload; R1's
  signed document remains archived and cannot complete R2.
- **AC-047:** Given a returned attempt, editing/resubmitting preserves
  its old payload, source hash, assignment, and decision in history.
- **AC-048:** Given one configured month deadline, submissions from all
  Pegawai use that same configuration irrespective of source path.
- **AC-049:** Given deadline 2026-02-10T17:00:00 Asia/Makassar, first
  submission at 2026-02-10T09:00:00Z is on_time; one at
  2026-02-10T09:00:00.000001Z is late and still accepted.
- **AC-050:** Given no deadline at first submission, classify
  deadline_not_set; a later deadline does not reclassify that record.
- **AC-051:** Given first accepted submit with deadline D, later edits
  and returns/revisions preserve that original time, D, and classification.
- **AC-052:** Given validation/storage failure on first submit, no first
  submission time/classification is captured and state remains editable.
- **AC-053:** Given a future period draft, save succeeds but submit is
  422 period_not_submittable; current and past months may submit.
- **AC-054:** Given January 2026 PKP, approve at January 31 23:59:59
  Asia/Makassar returns 409 period_not_finished; approve at February 1
  00:00:00 is eligible if other conditions pass. Both source paths apply.
- **AC-055:** Given missing historical rank/job/work unit, submit is 422
  and draft remains; adding applicable history permits subsequent submit.
- **AC-056:** Given histories before/after January 31, select the latest
  date <= January 31. Conflicting equal-date records block submission.
- **AC-057:** Given changed applicable identity after submit, approve is
  409 identity_changed; owner refresh closes old attempt and requires
  resubmission, preserving initial lateness and old attempt evidence.
- **AC-058:** Given content reassignment, only the new eligible allowed
  assessor can decide; reason/history are mandatory and old decisions fail.
- **AC-059:** Given approval wins a reassignment race, content reassignment
  cannot alter that approval. If reassignment wins, old approval is 403.
- **AC-060:** Given an unavailable approved-content assessor, admin can
  assign an allowed replacement inspector; original actor/score stay fixed.
- **AC-061:** Given pending final PDF, inspector return requires a reason
  and owner replacement; inspector acceptance requires explicit approved
  content confirmation and atomically completes that revision. This applies
  to new post-approval candidates in all three source-matrix paths.
- **AC-062:** Given a replaced candidate or revoked inspector, a stale
  decision cannot bind the old file or change content approval.
- **AC-063:** Given accepted final A and pending replacement B in the same
  official revision, A remains complete until B accepted; return of B
  preserves A. Apply to application, unsigned external, and originally
  signed external revisions. Missing/false owner unchanged-content
  declaration is 422 content_revision_required with no new binding.
  Missing/false inspector confirmation is 422 content_not_confirmed with
  candidate still pending. If B changes assessment content, return it for
  a new content revision. B acceptance preserves original source/approval,
  score, actor, and archived A. An old revision's file cannot supply fallback.
- **AC-064:** Given PDF of exactly 10,485,760 bytes and valid structure,
  size validation accepts; 10,485,761 bytes is rejected with no replacement.
- **AC-065:** Given corrupt, zero-byte, zero-page, or password-required
  PDF, upload is 422 and prior file/metadata stay intact.
- **AC-066:** Given validator unavailable or timeout/output limit,
  return respectively 503 pdf_validator_unavailable or
  422 pdf_validation_limit, preserving state and no bound orphan metadata.
- **AC-067:** Given mocked process results, distinguish qpdf --check
  errors/warnings from --requires-password's documented 0/2/3 statuses;
  real fixtures also prove structural validation and positive page count.
- **AC-068:** Given differing units or excessive decimal scale, reject
  input without unit conversion or rounding; shared unit persists in both
  target/realization columns on screen and print.
- **AC-069:** Given maximum counts/text/numbers, valid bounds pass;
  one over bounds, exponent notation, NaN, infinity, or negative inputs fail.
- **AC-070:** Given incomplete draft fields, draft save persists nulls;
  application submit is 422. External submit still needs no activity rows.
- **AC-071:** Given User.status 0, unverified, or soft-deleted actor,
  PKP mutations are denied. Pegawai category alone never grants authority.
- **AC-072:** Given admin without actual assignment, content/document
  decisions are 403 even with configuration/reassignment permissions.
- **AC-073:** Given nested UUID belonging to another PKP/revision, return
  404 and do not expose/change that resource or stream its bytes.
- **AC-074:** Given repeating 200/3, display 66,666666... with exact
  200/3 detail on screen and PDF. Terminating 90.991 displays 90,991;
  neither presentation changes predicate or rational storage.
- **AC-075:** Given long activities spanning pages, approved application
  PDF repeats headers and retains all text, summaries, and signature block.
- **AC-076:** Given draft print request, return 409 revision_not_approved;
  an approved archived application revision remains printable by authorized
  actors from its snapshot. External source bytes remain unchanged.
- **AC-077:** Given mode change in a correction, start empty appropriate
  inputs and preserve base approval/history; mode mutation on save is 422.
- **AC-078:** Given source candidate replacement after return, old
  declared-signed metadata does not transfer; new declaration/review apply.
  For a returned post-approval final candidate, upload a fresh document
  with its own unchanged-content declaration; retain the accepted original
  until inspection acceptance. Never rewrite an approved source PDF.
- **AC-079:** Given orphan cleanup, bound/current/archived evidence is
  retained; failed unreferenced staging may be removed after binding checks.
- **AC-080:** Given concurrent deadline edit/submit, lock order yields one
  effective deadline snapshot from the committed ordering, never a hybrid;
  previously submitted owners keep their first-submission classification.
- **AC-081:** Given a linked unassigned super-admin and actual global
  Gate::before allowing that actor, content/document decision HTTP routes
  still return 403 through PkpAuthorization. Domain policies, Gate/provider,
  relationship resolution, and services are not stubbed or bypassed.
- **AC-082:** Given verified active admin/super-admin with required direct
  permissions and no Pegawai, configuration read/write and authorised
  all-record listing succeed; personal create/submit/assessment is 403.
  A missing required permission still denies the administrative action.
- **AC-083:** Given ordinary owner, own-options returns only that owner's
  eligible allowed alternatives and eligible default; admin settings of
  another employee are denied. A subsequently revoked option fails submit.
- **AC-084:** Given ordinary user or administrative account, desktop/mobile
  navigation reaches its authorized Blade pages. Owner can create, edit,
  choose an alternative, submit, and view field errors without accessing
  admin settings. Submitted/approved edit remains 409 revision_not_editable.
- **AC-085:** Given absent or unset version-0 configuration, configure
  with expected_version 0 succeeds once and produces version 1; a second
  stale zero returns 409. PKP mutations with zero/negative version are 422.
- **AC-086:** Given the planned Nginx/PHP/qpdf stack, upload of valid PDF
  exactly 10 MiB reaches Laravel and succeeds with authentication/CSRF;
  10 MiB + 1 byte within the request envelope reaches Laravel and is 422.
  The accepted prior document is preserved on rejection. A request over
  the proxy envelope may return 413 before Laravel and creates no metadata.
- **AC-087:** Given the dedicated concurrency configuration, its actual
  active connection is pgsql or mysql as selected, never sqlite. Two
  independent connections observe committed fixtures and race through the
  public transitions. Wrong-driver/missing-service configuration fails;
  no skipped concurrency tests or hidden in-memory substitution.
- **AC-088:** Given PHP-FPM runtime and PHP/browser CI upload jobs, qpdf
  is available at the configured executable path and real PDF fixtures
  exercise its inspection. Missing qpdf causes the specified failure, not
  a mocked successful upload or a skipped integration test.
- **AC-089:** Given existing Cuti/Izin/role-management authorization,
  adding PkpAuthorization preserves global Gate behavior and established
  permissions/validation/approval flows; full applicable regression gates
  remain required. No broad auth-provider bypass removal is performed.
- **AC-090:** Given a 500-activity allowed tree saved from the Blade UI,
  JSON persistence retains all validated activities/order; form-variable
  truncation cannot make a partial submission appear successful.

### 6. Test Automation Strategy & Pre-Agreed Test Seams

#### 6.1 Test Seams Matrix

- Requirements: REQ-001..004; Public boundary: PKP web create/edit/detail
  routes; Level: Feature; Mocking rules: Real DB, real validation and
  ownership

- Requirements: REQ-005..006; Public boundary: submit and decision routes;
  Level: Feature; Mocking rules: Real policies, services, DB transactions

- Requirements: REQ-007; Public boundary: approved PDF route; Level: Feature +
  browser; Mocking rules: Real DomPDF; inspect text and render layout

- Requirements: REQ-008..010; Public boundary: upload/download and revision
  routes; Level: Feature; Mocking rules: Storage fake allowed for failure
  scenarios

- Requirements: SEC-001..006; Public boundary: HTTP routes and PkpPolicy
  methods; Level: Feature + Unit; Mocking rules: No policy bypass or internal
  authorization mocks

- Requirements: Concurrency; Public boundary: simultaneous submit/decision
  transactions; Level: Integration; Mocking rules: Real deployment-compatible
  DB connections

- Requirements: Scoring; Public boundary: Proposed PkpCalculator.calculate;
  Level: Unit; Mocking rules: Independent expected values; no domain mocks

- Requirements: User workflow; Public boundary: create, submit, assess, print,
  upload; Level: Playwright; Mocking rules: Real app; no successful-response
  stubs

#### 6.2 Complete Acceptance-Criteria Traceability

- **AC-001..008, AC-015, AC-018, AC-021..024, AC-026..031:** Create,
  save, submit, decision, and assessor-settings HTTP Feature seams.
  Real policies, Form Requests, services, and database.
- **AC-009..014, AC-016, AC-025, AC-038..043:** Approved PDF, upload,
  private download, and document decision Feature seams. Real PDF render
  and validator; Storage::fake only for controlled failure coverage.
- **AC-017, AC-019..020, AC-032..037:** Calculator Unit + HTTP input
  rejection. Pure exact arithmetic, independently stated rational oracles.
- **AC-044..047:** Revision-create, save/resubmit, approve, monthly detail
  Feature seams; AC-045 also two-connection integration concurrency.
- **AC-048..052, AC-080:** Deadline configure/read and submit/detail
  Feature seams with injected server clock; AC-080 actual database race.
- **AC-053..057:** Submit/decision/refresh-identity Feature seams using
  real history rows and deterministic Asia/Makassar clock boundaries.
- **AC-058..063:** Content reassignment, inspector reassignment, upload,
  document decision and detail Feature seams; AC-059/062 also races on
  actual DB. Test revoked authority and unchanged content snapshots.
  AC-063 must use all three source-matrix paths, including signed external
  source reuse followed by final-only replacement. Exercise both declaration
  failures, returned content mismatch, approved original retention, and
  actual acceptance changing only current final download binding.
- **AC-064..067:** Upload Feature seams with real qpdf fixtures. Adapter
  Unit tests may fake process exit/timeout/unavailable outcomes; this must
  not replace real parser integration coverage or suppress failures.
- **AC-068..073:** Save/submit and policy/HTTP Feature seams for bounds,
  unit consistency, actor eligibility, permissions, and nested membership.
  AC-071 completion requires actual HTTP mutation denial/no state change
  with the real provider and middleware, not only a Unit predicate result.
  Include disabled, unverified, and soft-deleted accounts; respect existing
  auth/verification middleware responses and assert explicit domain 403
  where that layer handles the rejection. Positive generic Gate results
  cannot substitute for the required PKP rejection.
- **AC-074..076:** Calculator Unit, Blade detail, and real PDF Feature
  seams plus rendered layout inspection; browser print/download workflow.
- **AC-077..079:** Correction/save/upload/cleanup public service Feature
  seams with actual history and binding checks, controlled storage faults.
  AC-078 distinguishes editable source replacement before approval from
  a final replacement after approval; neither inherits another file's
  declaration or overwrites immutable reviewed bytes.
- **AC-081..083, AC-085, AC-089:** Actual-provider HTTP authorization,
  admin configuration, owner-options, and record mutations. Include
  unlinked admin fixtures built without createUserWithRole (which always
  creates Pegawai), linked/unlinked super-admin, permissions granted and
  absent, and a positive generic Gate result with denied PKP HTTP mutation.
- **AC-084, AC-090:** Blade create/edit/detail/settings HTTP and Playwright
  seams with normal owner/admin/assessor actors; real form data/options,
  desktop/mobile navigation, JSON persistence, and validation feedback.
- **AC-086, AC-088:** Authenticated HTTP integration against the actual
  reverse proxy, PHP runtime and private storage with real qpdf fixtures.
  Laravel's internal request test alone cannot prove proxy upload limits.
- **AC-087:** Dedicated two-connection PostgreSQL/MySQL test-suite seam,
  actual selected driver asserted before test setup and throughout workers.
- **REQ-001..016 / representative E2E:** Both authoring paths, admin
  settings/deadline, owner submit, assessor approval/return, official
  correction switch, signed-file inspection/replacement, and historical
  download. Run against real app; no response stubs or role bypasses.

#### 6.3 Mocking & Isolation Boundaries

Run business rules, Eloquent, Form Requests, and policy checks with real
implementations. SQLite tests follow the current phpunit.xml defaults;
they do not prove production locking behavior. Concurrency tests require
PostgreSQL 16 (compose.prod.yaml's selected engine) in an isolated test
database, plus MySQL 8 for the repository's default/Sail path. Verify the
actual deployment engine before release; do not inspect production for
tests or infer a live engine from configuration files.
Storage fakes and controlled write failures are permitted; at least one
private-storage integration test must prove public inaccessibility.

RED tests must fail for the intended missing behavior, not dependency
or environment errors. Test empty versus zero target, decimal precision,
overachievement, threshold edges, unequal indicator sizes, duplicate
periods, unauthorized download, stale revisions, failed writes, and
owner/assessor changes after approval. Use the confirmed scoring examples
as independent oracles; retain delegated provenance for the planning audit.
Add real qpdf fixtures and identity/assignment/deadline concurrency pairs.
PDF structural validation cannot be mocked in all Feature tests.

### 7. Project Structure & Executable Commands

Proposed locations: `app/Models/Pkp*.php`, `app/Policies/PkpPolicy.php`,
`app/Http/Requests/*PkpRequest.php`, `app/Services/Pkp*.php`,
`app/Http/Controllers/PkpController.php`, additive `database/migrations/`,
`resources/views/pkp/`, `tests/Feature/Pkp*Test.php`, and calculator
tests under `tests/Unit/`. These are planned seams, not existing symbols.
The validator's explicit typed result file is `app/Support/PdfInspection.php`;
the planner must allocate it and preserve its field/exception contract
without silently exceeding the chosen implementation packet size.

Additional target boundaries required by the consistency audit:

- `app/Services/PkpAuthorization.php`, `app/Policies/PkpPolicy.php`, and
  the read service for own assessor options. AuthServiceProvider's global
  super-admin Gate callback is inspected/regression-tested, not removed.
- `database/seeders/UserRolePermissionSeeder.php`: add direct PKP
  configuration permissions without granting assignment-based actions.
  Existing seeded accounts need no fabricated Pegawai link for admin use.
- `resources/views/layouts/navigation.blade.php` and actual active layout
  variants: PKP links outside the existing restricted group on both sizes.
  Confirm which layouts are rendered; do not update an unused navigation.
- `tests/Feature/Pkp*Test.php`, `tests/Unit/`, `e2e/`, plus the proposed
  `tests/Integration/Pkp/` suite and `phpunit.pkp-concurrency.xml`.
  Reuse existing fixtures where appropriate, but explicitly create the
  unlinked bootstrap-admin case instead of changing all shared helpers.
- `docker/production/nginx/nginx.conf`: permit the PKP multipart envelope
  of 16 MiB (16,777,216 bytes). Maintain existing application file limits
  for other modules and verify their regression behavior. Configure
  upload_max_filesize >= 10M and post_max_size >= 16M in supported PHP
  runtime recipes. Requests beyond the envelope return visible 413;
  reaching Laravel does not bypass the application's 10 MiB file check.
- `docker/common/php-fpm/Dockerfile`: provide qpdf in the final runtime
  stage, not only the builder. Other supported upload-serving recipes
  (`docker/apache/Dockerfile`, development workspace) require the same
  validator availability or an explicitly unsupported-runtime declaration.
- `.github/workflows/php-tests.yml`, `.github/workflows/playwright.yml`,
  or a dedicated PKP integration workflow: provide qpdf where real uploads
  are exercised and isolated PostgreSQL 16/MySQL 8 services for concurrency.
  Direct brick/math usage must become an explicit composer requirement.
  Do not suppress new gate failures or silently rely on transitives.

These are blast-radius requirements, not implementation task slices.
The plan must assign them to vertical user outcomes with RED/VERIFY seams.
No changes to these source/configuration files occur during this spec phase.

```bash
php artisan test --filter=Pkp
php artisan test
./vendor/bin/pint --test
npx playwright test --project=chromium
npm run build
composer audit
npm audit
```

No PHP static-analysis command is currently defined in composer.json.
Do not invent a passing typecheck gate; configuration changes require
an explicit plan. Application checks have not been run in this spec phase.
Validate this Markdown file with the available Markdown linter.

Runtime readiness checks required by the Code plan, not executed here:

```bash
qpdf --version
php artisan test --configuration=phpunit.pkp-concurrency.xml
```

The proposed concurrency config must not inherit phpunit.xml's forced
DB_CONNECTION=sqlite or DB_DATABASE=:memory:. It selects only
tests/Integration/Pkp with isolated test credentials/services provided by
the runtime job. Run it twice, selecting pgsql and mysql respectively.
Assertions must fail early on any other driver, unavailable service, or
non-isolated database; do not skip. Migrate the target database and commit
fixtures before starting workers; independent connections must not be
hidden inside one parent rollback-only transaction. Clean up only that
isolated test database. Keep ordinary SQLite Unit/Feature runs intact;
the full macro gate includes both ordinary and dedicated integration runs.
qpdf must be installed/provided by the runtime recipe before upload tests
or release readiness can be declared. Configure the absolute executable
path via project configuration. Uploaded file/request/post-size limits
must permit at least 10 MiB plus multipart overhead; the application still
enforces its 10,485,760-byte file boundary. No runtime installation occurs
as part of this document change.
Parser readiness applies to each process that runs real inspection:
PHP test runner, browser's application server, and final deployed image.
Before its first real PDF test, record the executable/version in that
actual process environment. A Dockerfile providing qpdf does not establish
availability in a separate host CI PHP process. The plan must place this
verified prerequisite before parser-dependent tests, without success stubs
or skipped coverage. This is a verification contract, not a task graph.

### 8. Implementation Guardrails

- **Always do:** Write RED tests first; preserve existing dirty Izin
  changes; use private files and locked transitions; run the applicable
  quality gates; update docs/ARCHITECTURE.md when adding the new seams.
- **Ask first:** Approve this remediated Draft and its implementation plan
  before coding. Existing user decisions and PROCEED resolutions do not
  require repeated clarification merely because they retain provenance.
- **Never do:** Skip tests, suppress failures, hard-code example personal
  data, treat upload as approval, or silently copy inconsistent formulas.

### 9. Rationale, Context & Architecture Decisions

The repository already provides UUID-backed Pegawai/User records,
Spatie RBAC, Blade pages, DomPDF, private Izin document handling, and
service/policy boundaries. The PKP module should follow those conventions
while keeping its single-assessor workflow separate from Izin.

Snapshotting submitted identity and approved calculations preserves
historical printouts when employee profiles, formulas, or activity
templates change. Revision-bound uploads make the distinction between
approved content and a signed scan explicit without pretending the
application has verified the scan's signature or contents.

The current Izin ADR remains unchanged and does not govern PKP decisions.
The exact score representation is documented here; no new ADR is required
for routine use of an installed arithmetic library or existing MVC seams.
New PKP vocabulary is isolated to this specification pending glossary
integration in an authorized documentation phase; existing glossary
definitions are neither replaced nor contradicted.

#### 9.1 Remediation Traceability and Projected Readiness

- **CF-001:** Sections 4.2 revision-create/decision, 4.4 official/pending
  bindings, 4.5 state rules; AC-044..047, AC-063, AC-077.
- **CF-002:** Sections 4.2 deadline configure/read, 4.4 snapshot schema,
  4.5 first-submission/time/concurrency; AC-048..054, AC-080.
- **CF-003:** Sections 4.1 policy, 4.2 reassign routes, 4.5 authority/races;
  AC-058..062, AC-071..072.
- **CF-004:** Sections 3.2 and 4.3 source-specific requirements;
  AC-005, AC-021..026, AC-070.
- **CF-005:** Section 3.1 actual history fields and effective identity,
  Sections 4.2 refresh/approve, 4.4 snapshot storage; AC-055..057.
- **CF-006:** Sections 4.2 upload, 4.6 bounded qpdf/storage behavior;
  AC-064..067 and Code runtime-readiness gates.
- **Refinement:** Shared units, repeating display, post-approval inspection,
  unavailable inspector, period eligibility, and deadline absence/boundary
  appear in Q10..Q19 and their route/state/calculator contracts.
- **Delegated audit Section 3:** Uniqueness/mode, official/pending selection,
  submitted evidence, permissions/eligibility, null drafts, bounds, rational
  arithmetic, layout, storage compensation, retention, and DB verification
  all have explicit contracts in Sections 3..7. No silent auto-resolution.

Version 0.2 author self-assessment was 92/100; its later independent
consistency review scored 78/100 and identified integration blockers.
Do not treat the former projection as an independent quality-gate result.
Version 0.3 projected readiness is **90/100**: Completeness 37/40,
Clarity 28/30, Alignment 25/30, with no remaining critical contract veto
found in the author contract review. Deductions retain glossary integration,
independent re-audit, and runtime/parser/database readiness work.
This is self-assessment, not implementation test success or user approval.

Proceed to `/tdd-plan-tasks` using this spec and remediation audit after
user approval; `/tdd-analyze` can independently verify blast radius and
contract consistency first. Retain new-session preference unless overridden.

#### 9.2 Consistency Audit Remediation Record (2026-10-06)

> [!SUCCESS]
> REMEDIATION STATUS: RESOLVED
> This record closes the in-chat v0.2 consistency findings at document
> contract level. That audit was not saved to docs/audit; its score 78/100
> remains the historical independent result. This version's 90/100 is a
> projected author score and must not be reported as an independent pass.

- **A-001, Gate bypass:** Section 4.1 defines PkpAuthorization, direct
  policy/permission predicates, locked service enforcement, and view
  capabilities. Global super-admin Gate behavior remains intact outside
  PKP; AC-081/089 prove real-provider HTTP denial and existing regressions.
- **A-002, bootstrap admin:** Administrative actions require eligible
  account plus explicit role/permission, not Pegawai ownership. Personal
  and assignment actions still require a real link. AC-082 and dedicated
  unlinked fixtures prevent createUserWithRole hiding this case.
- **A-003, owner choices/form flow:** Section 4.2 adds own-options,
  create/edit form contracts and administrative form rendering; Section
  4.3 defines accessible navigation and complete JSON persistence.
  AC-083/084/090 trace the ordinary owner's actual Blade/browser flow.
- **A-004, version zero:** Section 4.3 distinguishes PKP versions >= 1
  from configuration versions >= 0. AC-085 covers first configuration,
  stale zero, and forbidden record-zero payloads.
- **Integration scope:** Section 7 names the Nginx/PHP size envelope,
  final-stage/CI qpdf availability, dedicated DB config/services, fixture
  differences, active navigation, and explicit Composer dependency.
  AC-086..088 require actual runtime coverage rather than internal mocks.
- **Preserved scope:** Prior CF-001..006 closures and Q1..Q19 remain
  unchanged. There is no change to the approved business decisions,
  no implementation task plan authored, and no source/runtime modification.

At that earlier v0.3 remediation, the next audit had to evaluate version 0.3
rather than copy either self-score or the historical 78/100. No Plan existed
at that point. Plan v1.0 now exists and needs v0.4 rebaseline/remediation;
full Spec-to-Plan-to-tests execution evidence remains unproven.

#### 9.3 Plan Audit Specification Remediation (Version 0.4)

> [!NOTE]
> SPEC FINDING P-001 REMEDIATION STATUS: RESOLVED
> The user selected same-content signed-external final replacement with
> inspector review. The source matrix, declarations, responses, immutable
> source retention, and reconciled AC-039/040/061/063/078 close the missing
> specification rule. Execution approval and plan rebaseline remain pending.

- **P-001/D-SPEC-01:** Q20 and Section 4.2 source matrix explicitly permit
  same-content replacement in all three source paths. Initial signed-source
  approval reuse remains separate from review of newly uploaded candidates.
- **P-004 support:** Section 6.2 requires HTTP mutation evidence for AC-071;
  a Unit predicate alone cannot close the criterion. Its plan ownership
  must be updated by the planner, not silently reassigned in this phase.
- **P-005 support:** Section 4.3 identifies App\Support\PdfInspection and
  Section 7 its source path; schedule the owned contract without hiding
  an additional implementation file in a full five-file packet.
- **P-003 support:** Runtime verification explicitly applies before real
  parser tests to every relevant test/server/image process. The planner
  must fix its early-CI prerequisites and task dependencies.
- **P-002/P-006:** Bootstrap RED/GREEN task sequencing and flattened plan
  lists require `/tdd-plan-tasks`; this author has not edited the plan.

Projected Spec-only readiness is **91/100**: Completeness 38/40,
Clarity 28/30, Alignment 25/30. This is author self-assessment, not an
independent pass or a rescoring of the combined Spec/Plan audit. That
audit's historical 79/100 and remaining Plan blockers stay in force until
the actual plan is remediated, rebaselined, and independently checked.
Version 1.0 plan's v0.3 SHA is intentionally stale after this source update;
record v0.4 and its new checksum in the planner's next revision.
No new AC IDs are added: retain and update all 90 scenario mappings.

#### 9.4 Verified Technical References

- [brick/math 0.12.3 BigRational source](https://raw.githubusercontent.com/brick/math/0.12.3/src/BigRational.php):
  exact rational operations, compareTo, simplified, getNumerator, and
  getDenominator checked against the locked version's tagged source.
- [qpdf inspection and exit-status documentation](https://qpdf.readthedocs.io/en/stable/cli.html#pdf-inspection):
  structural check, password requirement, and page-count option semantics.
  Structural validity does not establish signature or content correctness.
- Repository evidence: composer.json/composer.lock, config/database.php,
  compose.prod.yaml, docker-compose.yml, Pegawai/User/Riwayat models,
  CONSTRAINTS.md, and the audit linked in front matter. Configuration
  shows supported paths, not observed production runtime health.
