---
title: Izin Controller Refactor (Behavior-Preserving)
version: 1.3
date_created: 2026-09-24
last_updated: 2026-09-24
status: Approved
upstream_prd: none (PRD bypassed — see §1.2)
target_plan: plan/plan-izin-controller-refactor.md
---

# Technical Specification: Izin Controller Refactor (Behavior-Preserving)

> [!NOTE]
> **Remediated on 2026-09-24** in response to `docs/audit/clarification-report-izin-controller-refactor-2026-09-24.md`. Decisions **D-001**, **D-002**, and **D-003** are recorded in §1.2; the shared request-rule trait was brought into scope in §1.1; and the missing contract pins raised as **C1** and **C8** are now stated in §3.1, §4.2, and §5.8.
>
> **Remediated again on 2026-09-24** in response to `docs/audit/consistency-audit-izin-controller-refactor-2026-09-24.md`. **MC-1** is closed by the new create-form render contract in §4.3 and AC-042–AC-045; **MC-2** is closed by resolving the trait path in §7 (decision **D-004**); and **KC-1**/**KC-2** are closed by the reversal record in §9.
>
> **Approved on 2026-09-24** (version 1.0 → 1.1). This closes plan assumption `ASSUMPTION-007`, which required the frozen baseline to be an approved specification rather than a draft. Execution has started: Phase 1 of `plan/plan-izin-controller-refactor.md` (Tickets 001, 002, and 012) is complete and committed, so AC-017 through AC-020, AC-034 through AC-037, AC-039, and AC-042 through AC-045 are now pinned by dedicated tests rather than by oracle claims alone.
>
> **Remediated a third time on 2026-09-24** (version 1.1 → 1.2) in response to `docs/audit/consistency-audit-izin-controller-refactor-phase3-2026-09-24.md`. **MC-3** is closed on the specification side: §1.1 no longer exempts `docs/ARCHITECTURE.md`, and the map has been updated. Execution has also advanced past Phase 1 — Tickets 003, 004, 005, and 006 are complete and committed, with `IzinSubmissionService` now the only implementation of the PERMA submission rules.
>
> **Remediated a fourth time on 2026-09-24** (version 1.2 → 1.3), closing TASK-205 and TASK-206 of `plan-refactor-izin-submission-v1.0.md`. **AC-001** and **CON-006** now define the boundary precisely instead of relying on the undefined word "pre-existing": an **inherited oracle file** is one tracked at the characterization baseline `27c1467` and must never be touched, while a **refactor-owned file** introduced by §6.4 may grow as tickets add assertions. That distinction was not academic — during execution it twice decided whether a task was legal, and in both cases the task as written would have breached the boundary. §6.4 and §7 were also brought in line with reality: `IzinPermaCapTest` added, the `phpunit.xml` test-isolation hardening recorded with its justification (audit ORPH-01), and the phase-3 audit report listed (ORPH-02).

## 1. Purpose & Scope

This specification defines a **strictly behavior-preserving refactor** of `app/Http/Controllers/IzinController.php`. The controller currently duplicates the PERMA submission normalization rules between `store()` and `update()`, repeats an authorization decision that `IzinPolicy` already makes, re-resolves the same `Izin` row up to four times per update request, and carries three unreachable branches (`$allowEdit`, the `abort(500)` delete guard, and the `?? $izin->uuid` PDF filename fallback). The PERMA validation rules are additionally duplicated line-for-line between `StoreIzinRequest` and `UpdateIzinRequest`.

The refactor introduces one new public seam — `App\Services\IzinSubmissionService` — plus one shared request-rule trait, so that the submission rules have a single source of truth, and reduces the controller to request/response orchestration that delegates to existing services.

The refactor is guarded by the existing characterization suite for the Izin domain: **10 feature files and 5 unit files, 320 passing tests and 695 assertions** across the whole suite. That suite was built specifically to make this refactor safe and is already green before any code changes.

> [!IMPORTANT]
> This is a refactor, not a feature. Every observable HTTP behavior — route names, URLs, HTTP verbs, status codes, redirect targets, flash keys and messages, validation error keys and messages, rendered markup, and persisted column values — MUST remain byte-identical. Any requirement that would change observable behavior is explicitly deferred in §1.1.

### 1.1 Out of Scope

- **No route changes.** Paths, route names, HTTP verbs, middleware (`auth`, `verified`), and route ordering stay exactly as defined in `routes/web.php:91-104`.
- **No schema changes.** No migration, no column addition, no index change, no constraint change.
- **No Blade markup or copy changes.** This includes the pinned legacy quirks in `resources/views/izin/edit.blade.php` (the admin placeholder branch with no submit control, and the commented `<!-- Rest of the form fields -->` markers).
- **No change to effective validation behaviour.** `StoreIzinRequest` and `UpdateIzinRequest` must keep producing exactly the same rules, error keys, and error messages as today. Their line-for-line identical `jenis()`, `jenisRules()`, and `messages()` **are relocated** into a single shared trait per REQ-011, while `rules()` stays per class. Relocation is in scope; changing any rule, error key, or message is not.
- **No changes to existing service or support behaviour.** `IzinApprovalService`, `IzinDocumentService`, `IzinQueryService`, `ApproverDirectoryService`, `WorkdayService`, and `IzinType` are consumers-only dependencies of this refactor.
- **No semantic change to `IzinPolicy`.** Only the controller's duplicated copy of a policy decision is removed.
- **Completing the regular edit form** — the non-approved branch of `izin.edit` renders only `jenis_izin` and `atasan_pimpinan_uuid`, so it cannot satisfy `UpdateIzinRequest::rules()` (which requires `tanggal_mulai`, `tanggal_selesai`, `alasan`, and `pimpinan_uuid`). The form is incomplete. This is recorded as a pinned legacy quirk and is **deferred**, not fixed here.
- **Other SDLC tracks** — Cuti domain extraction (discovery draft Track B), routes cleanup, dependency security (Track A), policies for other domains, view partial extraction, Docker/CI (Track D), and PERMA PDF template fidelity review.
- **`docs/ARCHITECTURE.md` is in scope, not exempt.** `IzinSubmissionService` is a new public test seam (§4.1), so the architecture map MUST be updated as part of this change: the `app/Services/` inventory in its §6 and the seam matrix in its §10. This corrects the earlier draft, which exempted the map unless a new *directory* appeared. That test was wrong twice over — it contradicted this document's own §4.1 and the Living Architecture Map Mandate in `AGENTS.md`, and it also failed on its own terms, because `spec/` and `plan/` are new root directories. The map was updated on 2026-09-24.

### 1.2 Open Questions & Assumptions

- **ASSUMPTION A1 — PRD bypassed:** No approved PRD exists in `docs/prd/`, and no `/spec/` directory existed before this document. The upstream brief is composed of `discovery-draft.md` (Track C, partially stale), `docs/refactor/legacy-plan-application.md` (characterization plan and its Completed Batch records), and the green Izin characterization suite. `AGENTS.md` permits bypassing the PRD phase when requirements are clear, with the Specification Architect performing the heavy lifting and marking guesses. Any remaining guess below is marked `[ASSUMPTION]`. This document was interrogated by `/tdd-clarify` on 2026-09-24; every finding raised is resolved and recorded in §1.2 and in `docs/audit/clarification-report-izin-controller-refactor-2026-09-24.md`.
- **ASSUMPTION A2 — `$allowEdit` is unreachable:** `IzinController::edit()` computes an `$allowEdit` expression after `authorize('update', $izin)`. Given `IzinPolicy::update()` admits only (a) `super-admin`/`admin`, or (b) the owner with both verification fields at `Belum Diverifikasi`, every request that survives `authorize()` also satisfies `$allowEdit`. Therefore the redirect-with-error branch is unreachable. This is a code-reading conclusion and MUST be proven by AC-016 before the branch is deleted.
- **ASSUMPTION A3 — the no-surat payload heuristic is preserved:** `UpdateIzinRequest::isNoSuratUpdate()` discriminates a No-Surat Update from a Full Update using `count($this->all()) <= 3`. The threshold is **not arbitrary**: it is sized for the real form payload of `_token` + `_method` + `no_surat_izin`, as documented by the source comment at `UpdateIzinRequest.php:65-66` (`csrf, method, and no_surat_izin only`). It is fragile but it is the current public contract, so the spec formalizes and pins it instead of replacing it. See `docs/adr/0001-izin-no-surat-update-discrimination.md`.
- **ASSUMPTION A4 — the `abort(500)` delete guard is kept:** `Izin` has no `deleting` observer, no soft deletes, and no `Izin::observe()` registration anywhere in `app/Providers/`, so `$izin->delete()` cannot currently return `false` and the `abort(500, 'Pengajuan izin gagal dihapus.')` branch is unreachable. It is retained as an explicit defensive failure rather than removed, because removing it would silently convert a future model-level veto into a success response. Rationale recorded in §9.
- **ASSUMPTION A5 — `jumlah_hari` re-computation in `store()` is redundant but preserved:** `store()` computes `WorkdayService::countWorkdays()` at line 100 and then computes it again inside the `Izin tidak masuk kerja` branch at line 112. The second call is redundant. The service collapses this to one computation; the resulting value is identical in all cases.
- **ASSUMPTION A6 — the `?? $izin->uuid` PDF filename fallback is kept:** `generatePdf()` builds the download name as `'Surat_Izin_'.$izin->pegawai->nama.'_'.($izin->no_surat_izin ?? $izin->uuid).'.pdf'`. Because `IzinPolicy::cetak()` requires `! empty($izin->no_surat_izin)`, the `?? $izin->uuid` branch is unreachable. Like A4 it is retained and pinned (AC-040) rather than removed, so that this refactor stays behavior-preserving.
- **ASSUMPTION A7 — the explicit `uuid` override is kept:** `Izin` uses the `HasUuids` trait (Laravel 10 ordered UUIDs) while `store()` explicitly assigns `Str::uuid()` (random v4) at `IzinController.php:97`. The explicit assignment MUST be preserved so the stored `uuid` value and format remain identical (AC-039).
- **D-001 (Resolved) — No-Surat Update discrimination:** the payload-size heuristic is kept, formalized, and memoized. A dedicated route (`PUT izin/{izin}/no-surat`) and an explicit marker field were both rejected because each changes the public update contract and would invalidate the characterization net. Recorded as ADR-0001 (meets the triple gate).
- **D-002 (Resolved) — shared PERMA request rules:** the line-for-line identical `jenis()`, `jenisRules()`, and `messages()` in `StoreIzinRequest` (`34-53`, `61-91`) and `UpdateIzinRequest` (`43-62`, `84-114`) are extracted into one shared trait; `rules()` stays per class because the two genuinely differ. D-002 fails the ADR triple gate (easy to reverse, unsurprising, established repository pattern) and is recorded as prose in §9 instead.
- **D-003 (Resolved) — query-count target:** the `<= 1` lookup bound is a **blocking** criterion (AC-021), enforced with a `DB::listen()` collector filtered to `izin` selects bound by `uuid`. Memoization is already mandatory to preserve the 404 contract (REQ-012), so the reduction from four lookups to one follows from that fix rather than being an independent optimization.
- **D-004 (Resolved) — shared trait location:** the trait lives at `app/Traits/JenisIzinRulesTrait.php` with namespace `App\Traits`. This is derived from the repository's only trait precedent — `app/Traits/UserTrackingTrait.php`, which uses `namespace App\Traits;` and the `XxxTrait` suffix. `App\Http\Requests\Concerns` was rejected because the repository has no such namespace and `AGENTS.md` requires adhering to existing style. D-004 is trivially reversible and therefore fails the ADR triple gate; rationale recorded in §9.
- **D-005 (Resolved) — create-form render contract:** the three create routes and the `formData()` keys they expose are now part of the pinned contract (§4.3, AC-042–AC-045). They were previously unpinned even though Ticket-009 of the plan edits all three actions that produce them, which the consistency audit raised as MC-1.

## 2. Definitions & Domain Model (Aligned with `CONTEXT.md`)

No `CONTEXT-MAP.md` exists, so this project uses a single root `CONTEXT.md`. The terms this specification depends on were resolved during the clarification session and are recorded in the root `CONTEXT.md`; they are repeated below for reading convenience.

### 2.1 Canonical Terms Resolved by This Specification

- **Izin**: An employee's request for permission to be absent from work, submitted through the application and subject to approval. _Avoid_: Permit, leave request, permission.
- **Jenis Izin**: The category of an Izin, drawn from the fixed taxonomy in `App\Support\IzinType`. The category determines the approval depth and the applicable PERMA No. 7/2016 rule. _Avoid_: Type, izin type, category.
- **Jenis Single-Level**: A Jenis Izin whose approval requires only the Atasan Pimpinan, so its approval is final at that step. Currently `Izin Keluar Kantor` and `Izin Pulang Cepat`. _Avoid_: One-level, atasan-only.
- **Jenis Dua-Level**: A Jenis Izin whose approval requires the Atasan Pimpinan and then the Pimpinan. Every jenis other than the single-level pair. _Avoid_: Two-step, multi-level.
- **Atasan Pimpinan**: The employee assigned to perform the first approval step of an Izin. _Avoid_: Supervisor, atasan, direct supervisor.
- **Pimpinan**: The employee assigned to perform the final approval step of a Dua-Level Izin. _Avoid_: Executive, leader, boss.
- **Pegawai**: An employee record in the organisation. _Avoid_: Employee, staff, user. (`User` is a separate authentication identity linked to a Pegawai.)
- **Nomor Surat Izin**: The official letter number recorded against an Izin after the Atasan Pimpinan has approved it. _Avoid_: Letter number, surat number, document number.
- **No-Surat Update**: An update request that changes only the Nomor Surat Izin of an already atasan-approved Izin. _Avoid_: Partial update, letter update.
- **Full Update**: An update request that changes the substantive attributes of an Izin that is not yet verified. _Avoid_: Complete update, regular update.
- **Hari Kerja**: A calendar day that counts as a working day for the purpose of Izin duration, as determined by `WorkdayService` against the recorded `Hari Libur`. _Avoid_: Business day, working day.

### 2.2 Relevant ADRs

- `docs/adr/0001-izin-no-surat-update-discrimination.md` — **Proposed.** Records why the payload-size heuristic for discriminating a No-Surat Update is preserved and formalized rather than replaced by a dedicated route or marker field. Written as part of this specification's remediation and traceable to decision D-001.

## 3. Requirements, Constraints & Guidelines

### 3.1 Functional Requirements

- **REQ-001**: The PERMA submission normalization rules — owner attribution, `jumlah_hari` calculation, single-level same-day normalization, and the `Izin Tidak Masuk Kerja` workday cap — MUST have exactly one implementation, exposed from `App\Services\IzinSubmissionService`.
- **REQ-002**: `IzinController::store()` MUST delegate all normalization in REQ-001 to `IzinSubmissionService` and MUST NOT contain any jenis-specific branching.
- **REQ-003**: `IzinController::update()` MUST delegate all normalization in REQ-001 to `IzinSubmissionService` and MUST NOT contain any jenis-specific branching.
- **REQ-004**: `IzinPolicy::update()` MUST remain the single authority for whether an Izin may be edited. `IzinController::edit()` MUST NOT recompute an edit decision.
- **REQ-005**: The No-Surat Update discrimination contract (`UpdateIzinRequest::isNoSuratUpdate()`) MUST keep its current observable semantics and MUST be evaluated at most once per request, with the resolved `Izin` instance reused by `authorize()`, `rules()`, and the controller action.
- **REQ-006**: The document lifecycle on Full Update MUST remain: store the replacement, persist the new filename, delete the superseded file, and clean up the just-stored file if persistence fails.
- **REQ-007**: `IzinSubmissionService` MUST be usable without an HTTP request and MUST NOT depend on the `Request` object, session, or authentication facade for its rule logic; the acting `User` is passed explicitly.
- **REQ-008**: The controller MUST invoke `IzinSubmissionService::resolveOwnerUuid()` before `prepareForCreate()`, so that normalization always receives a final `pegawai_uuid`. The order is an invariant, not an implementation detail.
- **REQ-009**: Document upload orchestration MUST remain in the controller and MUST continue to delegate to `IzinDocumentService::storeDocument()` and `delete()`. Upload handling MUST NOT move into `IzinSubmissionService`.
- **REQ-010**: The explicit `uuid` assignment in `store()` MUST be preserved. `Izin` uses the `HasUuids` trait, which would otherwise generate an ordered UUID; the current random v4 value assigned by the controller MUST remain the stored value.
- **REQ-011**: The line-for-line identical `jenis()`, `jenisRules()`, and `messages()` implementations in `StoreIzinRequest` and `UpdateIzinRequest` MUST be relocated into a single shared trait consumed by both classes. Each class MUST keep its own `rules()` method. The effective rules, error keys, and error messages MUST NOT change.
- **REQ-012**: The request-pipeline ordering contract for `izin.update` MUST be preserved: for an unknown `{izin}` UUID the response MUST be **404** regardless of the caller's authorization level, because resolution fails before any policy is evaluated. Only an existing `Izin` that the actor is not permitted to update MAY produce **403**.
- **REQ-013**: The No-Surat Update payload-shape contract MUST be preserved and documented: a request is a No-Surat Update only when the payload contains at most three entries, which for the real edit form means `_token`, `_method`, and `no_surat_izin`. Any additional field MUST move the request into the Full Update path.

### 3.2 Security Requirements

- **SEC-001**: Mass-assignment safety MUST be preserved. Every attribute written to `Izin` MUST originate either from `$request->validated()` or from `IzinSubmissionService` output, and the resulting key set MUST be a subset of `Izin::$fillable`. No raw request input may reach the model.
- **SEC-002**: Authorization MUST NOT be weakened. `IzinPolicy` remains the only authority for `create`, `update`, `delete`, `view`, `verifyAtasan`, `verifyPimpinan`, and `cetak`. No `Gate::before` change, no `@can` bypass, no middleware substitution.
- **SEC-003**: Owner attribution MUST NOT become client-controllable for non-privileged actors. Only `super-admin` and `admin` may attribute an Izin to another Pegawai.
- **SEC-004**: Document path handling MUST remain delegated to `IzinDocumentService`; the controller MUST NOT construct storage paths.
- **SEC-005**: Authorization ordering MUST NOT regress. The 404-before-policy behaviour of REQ-012 MUST be preserved, because collapsing it into a policy denial would change how a caller distinguishes a missing resource from a forbidden one. No `Gate::before` change, no policy bypass, and no `withoutMiddleware()` usage may be introduced to make a test pass.

### 3.3 Technical Constraints

- **CON-001**: PHP and Laravel versions stay as declared in `composer.json` (PHP `^8.1`, Laravel `^10.10`). No new Composer or npm dependency may be introduced.
- **CON-002**: `php artisan test` MUST report 100% pass, and `./vendor/bin/pint --test` MUST report 0 formatting errors in changed PHP files (per `CONSTRAINTS.md §1`).
- **CON-003**: No suppression annotations, no skipped/`markTestSkipped` tests, no removed or weakened assertions, and no lowered thresholds (per `CONSTRAINTS.md §3`).
- **CON-004**: No migration and no schema change of any kind.
- **CON-005**: `npx playwright test --project=chromium` MUST NOT regress. Because no Blade markup changes, this is expected to be unaffected; it is still part of the verification gate.
- **CON-006**: The refactor MUST NOT modify or delete an **inherited oracle file** — any `tests/**/*Izin*` file that was already tracked at the characterization baseline commit `27c1467`. Files introduced by §6.4 are **refactor-owned** and may be extended as tickets add assertions. The inherited suite is the regression oracle and MUST be consumed as-is. AC-001 defines the same boundary; a change that touches an inherited file requires an explicit amendment to this specification first, not a judgement call during implementation.

## 4. Interfaces & Data Contracts

### 4.1 New Public Seam: `App\Services\IzinSubmissionService`

```php
<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class IzinSubmissionService
{
    /**
     * Resolve the Pegawai that owns the Izin being submitted.
     *
     * Contract:
     * - When the actor holds `super-admin` or `admin` AND a non-empty
     *   $requestedPegawaiUuid is supplied, that UUID is returned.
     * - Otherwise the UUID of the actor's own linked Pegawai is returned.
     * - When the actor has no linked Pegawai and the fallback path is taken,
     *   a ModelNotFoundException propagates (preserving current behaviour).
     *
     * Ordering: the caller MUST invoke this before prepareForCreate()
     * (REQ-008) so that normalization receives a final pegawai_uuid.
     */
    public function resolveOwnerUuid(User $actor, ?string $requestedPegawaiUuid): string;

    /**
     * Build the persistence-ready attribute set for a new Izin.
     *
     * @param  array<string, mixed>  $validated  Output of StoreIzinRequest::validated()
     * @return array<string, mixed>
     *
     * @throws ValidationException key `tanggal_selesai`, message
     *         "Izin tidak masuk kerja maksimal 2 (dua) hari kerja."
     */
    public function prepareForCreate(array $validated): array;

    /**
     * Build the persistence-ready attribute set for a Full Update.
     *
     * @param  array<string, mixed>  $validated  Output of UpdateIzinRequest::validated()
     * @return array<string, mixed>
     *
     * @throws ValidationException key `tanggal_selesai`, message
     *         "Izin tidak masuk kerja maksimal 2 (dua) hari kerja."
     */
    public function prepareForFullUpdate(array $validated): array;
}
```

#### 4.1.1 `prepareForCreate` Behavioural Contract

Given `$validated` containing at least `jenis_izin`, `tanggal_mulai`, `tanggal_selesai`, and `pegawai_uuid`:

1. `jumlah_hari` = `WorkdayService::countWorkdays(tanggal_mulai, tanggal_selesai)`.
2. When `IzinType::isSingleLevel(jenis_izin)` is true, the returned set MUST have `jumlah_hari` = `0`, `tanggal_mulai` = `now()->toDateString()`, and `tanggal_selesai` = `now()->toDateString()`.
3. Else, when `jenis_izin` equals `IzinType::TIDAK_MASUK`, the workday count MUST be compared once against `IzinType::maxWorkdays(IzinType::TIDAK_MASUK)`; when it exceeds the cap, a `ValidationException` with the error key `tanggal_selesai` and the message `Izin tidak masuk kerja maksimal 2 (dua) hari kerja.` MUST be thrown.
4. The returned set MUST also contain the initial approval state: `status` = `Diajukan`, `verifikasi_atasan` = `Belum Diverifikasi`, `verifikasi_pimpinan` = `Belum Diverifikasi`. These values MUST overwrite any same-named keys present in `$validated`.
5. Keys present in `$validated` that are not named above MUST be passed through unchanged.
6. The method MUST NOT read the `Request`, session, or authentication state.

#### 4.1.2 `prepareForFullUpdate` Behavioural Contract

1. `jumlah_hari` = `WorkdayService::countWorkdays(tanggal_mulai, tanggal_selesai)`.
2. Steps 2 and 3 of §4.1.1 apply unchanged, with the workday cap evaluated against the value computed in step 1.
3. The returned set MUST NOT contain, and MUST NOT alter, `status`, `verifikasi_atasan`, `verifikasi_pimpinan`, `tanggal_verifikasi_atasan`, `tanggal_verifikasi_pimpinan`, or `no_surat_izin`.
4. Keys present in `$validated` that are not named above MUST be passed through unchanged.
5. The method MUST NOT read the `Request`, session, or authentication state.

#### 4.1.3 `resolveOwnerUuid` Behavioural Contract

| Actor roles | `$requestedPegawaiUuid` | Returned value |
| --- | --- | --- |
| `super-admin` | non-empty UUID | `$requestedPegawaiUuid` |
| `admin` | non-empty UUID | `$requestedPegawaiUuid` |
| `super-admin` or `admin` | `null` or empty | actor's own Pegawai UUID |
| any other role | non-empty UUID | actor's own Pegawai UUID |
| any other role | `null` or empty | actor's own Pegawai UUID |

The "empty" test MUST match PHP `empty()` semantics, so `null`, `''`, `'0'`, and `0` all select the fallback path. The fallback MUST use `$actor->pegawai()->firstOrFail()->uuid`, preserving the current `ModelNotFoundException` behaviour when the actor has no linked Pegawai.

### 4.2 Formalized Contract: `UpdateIzinRequest::isNoSuratUpdate()`

The method keeps its current observable semantics and gains an explicit contract plus memoization.

```php
/**
 * A request is a No-Surat Update when ALL of the following hold:
 *  1. the route-bound Izin exists;
 *  2. that Izin has verifikasi_atasan === 'Disetujui';
 *  3. the request payload contains a `no_surat_izin` key;
 *  4. the request payload contains at most 3 entries (count($this->all()) <= 3).
 *
 * Condition 4 is sized for the real edit form, whose payload is exactly
 * `_token` + `_method` + `no_surat_izin` (REQ-013). Adding any field to that
 * form silently moves the submission into the Full Update path.
 *
 * The predicate is memoized for the lifetime of the request. The resolved
 * Izin instance is exposed via resolvedIzin() so authorize(), rules(), and
 * the controller action share one database lookup.
 */
public function isNoSuratUpdate(): bool;

/**
 * The Izin resolved for the current request, or null when the route
 * parameter does not match a record. Memoized; at most one lookup per request.
 *
 * authorize() MUST consume this accessor and MUST fail with a 404 - not a
 * policy denial - when it returns null, preserving REQ-012 and SEC-005.
 */
public function resolvedIzin(): ?Izin;
```

> [!WARNING]
> Condition 4 is a payload-size heuristic, not an intent signal. It is preserved because changing it changes the public update contract. Any concurrent change to the edit form that adds a field to the no-surat submission would silently flip the request into the Full Update path. This risk is the subject of ADR-0001 and is pinned by AC-019 and AC-034.

### 4.3 Controller Target Shape (Responsibility Map)

`IzinController` keeps its current 17 public actions, constructor-injected services, and the `auth` middleware. Responsibilities move as follows:

| Action | Reads | Delegates to | Owns |
| --- | --- | --- | --- |
| `index` | — | `IzinQueryService::forUser()`, `IzinPolicy::viewAny` | pagination, view |
| `create` | `Auth::user()->pegawai` | — | missing-pegawai redirect via the shared guard helper, view |
| `edit` | — | `IzinPolicy::update` | view (no local edit decision) |
| `store` | validated input | `IzinSubmissionService::resolveOwnerUuid`, `IzinSubmissionService::prepareForCreate`, `IzinDocumentService::storeDocument` | persistence, redirect, flash |
| `show` | — | `IzinPolicy::view` | view |
| `update` | validated input, `UpdateIzinRequest::isNoSuratUpdate()` | `IzinSubmissionService::prepareForFullUpdate`, `IzinDocumentService::storeDocument/delete` | persistence, superseded-document cleanup, redirect, flash |
| `destroy` | — | `IzinPolicy::delete`, `IzinDocumentService::delete` | persistence, defensive 500 guard, redirect, flash |
| `verifikasiAtasan`, `verifikasiPimpinan` | — | respective policy | view |
| `prosesVerifikasiAtasan`, `prosesVerifikasiPimpinan` | validated input | respective policy, `IzinApprovalService::applyAtasan/applyPimpinan` | redirect, flash |
| `generatePdf` | — | `IzinType::pdfTemplate`, `PDF` facade | filename, download |
| `downloadDocument` | — | `IzinPolicy::view`, `IzinDocumentService::exists/download` | 404 guard |
| `createKeluarKantor`, `createTidakMasuk`, `indexKeluarKantor`, `indexTidakMasuk` | — | `IzinType`, `IzinQueryService::forUser` | redirect, pagination, view |

Structural assertions that MUST hold after the refactor:

- `IzinController.php` contains zero occurrences of `isSingleLevel` and zero occurrences of `maxWorkdays`.
- `IzinController.php` contains zero occurrences of `WorkdayService`.
- `IzinController.php` contains zero occurrences of the string `$allowEdit`.
- `IzinController.php` contains exactly one occurrence of the string `Data pegawai tidak ditemukan`, shared by `create()`, `createKeluarKantor()`, and `createTidakMasuk()` through one private helper (REQ-011 / M5).
- `IzinController.php` still contains the string `Pengajuan izin gagal dihapus.` — the A4 defensive guard is retained, not deleted (AC-038).
- `IzinController.php` still contains `$izin->no_surat_izin ?? $izin->uuid` — the A6 fallback is retained (AC-040).
- `StoreIzinRequest.php` and `UpdateIzinRequest.php` each declare their own `rules()` method and consume the shared trait; neither retains a local `jenisRules()`, `messages()`, or `jenis()` definition (REQ-011).

#### 4.3.1 View Data Contract (must not change)

`formData()` supplies the create and edit forms. Its keys and their types are a contract with four Blade views and MUST NOT be renamed, merged, or retyped.

| Key | Type | Consumers |
| --- | --- | --- |
| `pegawai` | `Pegawai` | create and edit form layouts |
| `jenisIzin` | `list<string>`, **except** in `createKeluarKantor()` where it is a single `string` | `create.blade.php:32`, `edit.blade.php:60`, `create-keluar-kantor.blade.php:33,39` |
| `pimpinanList` | collection of approver rows | `create.blade.php:59`, `create-tidak-masuk.blade.php:74` |
| `atasanList` | collection of approver rows | `create.blade.php:46`, `create-tidak-masuk.blade.php:61`, `create-keluar-kantor.blade.php:52`, `edit.blade.php:74` |

> [!WARNING]
> `jenisIzin` is deliberately polymorphic. `createKeluarKantor()` replaces the list with the scalar `IzinType::KELUAR_KANTOR` because `create-keluar-kantor.blade.php` renders it as a fixed hidden and disabled value rather than a dropdown. A consistency tidy-up that normalizes this to a list WILL break that view. The override must be preserved verbatim.

Each of the three create routes MUST continue to return the same view name and HTTP status, expose the same `formData()` keys, and — when the acting user has no linked Pegawai — redirect to `izin.index` with the `error` flash `Data pegawai tidak ditemukan`. All three guards are deduplicated into one helper by REQ-011 without changing any of those three observable outcomes.

### 4.4 Data Storage Schema

**No DDL change.** No migration, no column, index, or constraint modification is permitted by this specification.

For reference only, the existing columns whose values this refactor must keep identical are:

```sql
-- Existing table: izin  (no change in this specification)
-- uuid                   CHAR(36)  PRIMARY KEY
-- pegawai_uuid           CHAR(36)  NOT NULL   -- REQ-001 owner attribution
-- jenis_izin             VARCHAR    NOT NULL  -- selects the approval depth
-- tanggal_mulai          DATE       NOT NULL  -- normalized for single-level jenis
-- tanggal_selesai        DATE       NOT NULL  -- normalized for single-level jenis
-- jam_mulai              TIME       NULL
-- jam_selesai            TIME       NULL
-- jumlah_hari            INT        NOT NULL  -- derived by IzinSubmissionService
-- alasan                 TEXT       NOT NULL
-- dokumen                VARCHAR    NULL      -- managed by IzinDocumentService
-- no_surat_izin          VARCHAR    NULL      -- written only by the No-Surat Update path
-- status                 VARCHAR    NOT NULL  -- Diajukan | Disetujui Atasan | Disetujui | Ditolak Atasan | Ditolak
-- verifikasi_atasan      VARCHAR    NOT NULL  -- Belum Diverifikasi | Disetujui | Ditolak
-- verifikasi_pimpinan    VARCHAR    NOT NULL  -- Belum Diverifikasi | Disetujui | Ditolak
-- atasan_pimpinan_uuid   CHAR(36)   NOT NULL
-- pimpinan_uuid          CHAR(36)   NOT NULL  -- unused by the approval flow for single-level jenis
```

## 5. Acceptance Criteria & Testable Behaviors

Every criterion below is stated so that it can be verified by an automated test or a deterministic command. Criteria marked **(oracle)** are satisfied by the pre-existing characterization suite and constitute the primary no-regression guarantees.

### 5.1 Structure

- **AC-001 (oracle)**: No **inherited oracle file** may be modified or deleted. An inherited oracle file is any file matching `tests/**/*Izin*` that was already tracked at the characterization baseline commit `27c1467`. A **refactor-owned file** is one introduced by §6.4; those MAY grow as later tickets add assertions to them, because growing them is how this specification pins its own structural goals. Verified by `git diff --stat 27c1467 -- tests/`, which must list refactor-owned files only.
- **AC-002**: `IzinController.php` satisfies every structural assertion enumerated in §4.3, including the deduplicated missing-pegawai guard and the retained A4 and A6 branches.
- **AC-003**: `App\Services\IzinSubmissionService` is a non-`final` class exposing exactly the three public methods of §4.1, and is resolvable from the container without construction arguments.
- **AC-004**: `IzinSubmissionService` has dedicated unit coverage that exercises §4.1.1–§4.1.3 without issuing an HTTP request.

### 5.2 Submission Normalization

- **AC-005**: Given a Jenis Single-Level payload whose `tanggal_mulai` and `tanggal_selesai` are distinct dates, When `IzinSubmissionService::prepareForCreate()` is called, Then the returned set has `jumlah_hari` equal to `0` and both date keys equal to `now()->toDateString()`.
  > [!NOTE]
  > The date normalization is exercised at the service seam because the HTTP seam cannot reach it: `StoreIzinRequest::jenisRules()` enforces `date_equals:today` on both date fields for single-level jenis, so a distinct-date payload is rejected before the controller runs. The `jumlah_hari = 0` half of this rule is observable at the HTTP seam and is additionally pinned by `IzinPermaValidationTest`.
- **AC-006**: Given an `Izin Tidak Masuk Kerja` payload spanning more than two Hari Kerja, When `store` is called, Then a validation error is raised on the key `tanggal_selesai` with the message `Izin tidak masuk kerja maksimal 2 (dua) hari kerja.`, and no `izin` row is created.
- **AC-007**: Given an `Izin Tidak Masuk Kerja` Full Update payload spanning more than two Hari Kerja, When `update` is called, Then a validation error is raised on the key `tanggal_selesai` with the same message, and the stored row is unchanged.
- **AC-008**: Given a `super-admin` or `admin` actor supplying another Pegawai's UUID, When `store` is called, Then the row is attributed to the supplied Pegawai. Given the same actor with `null` or empty `pegawai_uuid`, When `store` is called, Then the row is attributed to the actor's own Pegawai. Given a non-privileged actor supplying another Pegawai's UUID, Then the row is attributed to the actor's own Pegawai.
- **AC-009 (oracle)**: Given a `store` payload that also supplies `status`, `verifikasi_atasan`, and `verifikasi_pimpinan`, When `store` is called, Then the persisted values are `Diajukan`, `Belum Diverifikasi`, and `Belum Diverifikasi`.
- **AC-010**: Given a Full Update payload, When `update` is called, Then `status`, `verifikasi_atasan`, `verifikasi_pimpinan`, and `no_surat_izin` retain their stored values.
- **AC-011**: Given an `Izin Tidak Masuk Kerja` payload spanning exactly two Hari Kerja, When `store` is called, Then `jumlah_hari` equals `2` and no validation error is raised.
- **AC-012**: Given a `store` payload for a Jenis Dua-Level that is not `Izin Tidak Masuk Kerja`, When `store` is called with an `alasan` of 900 characters, Then no validation error is raised and the full `alasan` is persisted.

### 5.3 Edit Authorization

- **AC-013 (oracle)**: Given the owner and an Izin with both verification fields at `Belum Diverifikasi`, When `GET izin.edit` is requested, Then the `izin.edit` view renders with status 200.
- **AC-014 (oracle)**: Given the owner and an Izin with `verifikasi_atasan` = `Disetujui` and empty `no_surat_izin`, When `GET izin.edit` is requested, Then the response is 403.
- **AC-015 (oracle)**: Given an `admin` and an Izin with `verifikasi_atasan` = `Disetujui` and `verifikasi_pimpinan` = `Belum Diverifikasi`, When `GET izin.edit` is requested, Then the `izin.edit` view renders and shows `Tambah Nomor Surat Izin`, the `no_surat_izin` input, the `<!-- Full form for admins -->` marker, and no `Simpan Nomor Surat` control.
- **AC-016**: A unit test enumerating the matrix of {`super-admin`, `admin`, owner-Pegawai user, assigned atasan, assigned pimpinan, unrelated user} × {`verifikasi_atasan` ∈ {`Belum Diverifikasi`, `Disetujui`}} × {`verifikasi_pimpinan` ∈ {`Belum Diverifikasi`, `Disetujui`}} × {`no_surat_izin` ∈ {`null`, non-empty}} asserts that whenever `IzinPolicy::update()` returns `true`, the removed `$allowEdit` expression would also have returned `true`. This is the precondition for AC-002's `$allowEdit` assertion.

### 5.4 No-Surat Update and Full Update Discrimination

- **AC-017 (oracle)**: Given an atasan-approved Izin and a payload containing only `no_surat_izin`, When `update` is called, Then the response redirects to `izin.index`, the flash key is `success` with the value `Nomor surat izin berhasil diperbarui`, and only `no_surat_izin` changed.
- **AC-018 (oracle)**: Given an atasan-approved Izin and a `no_surat_izin` value already used by a different Izin, When the no-surat update is submitted, Then a validation error is raised on the key `no_surat_izin` and the stored value is unchanged.
- **AC-019**: Given an atasan-approved Izin and a payload containing `no_surat_izin` plus three additional keys, When `update` is called, Then the request is NOT treated as a No-Surat Update.
- **AC-020**: Given an Izin whose `verifikasi_atasan` is `Belum Diverifikasi` and a payload containing only `no_surat_izin`, When `update` is called, Then the request is NOT treated as a No-Surat Update.
- **AC-021 (blocking, D-003)**: A single `update` request performs at most one `select ... from izin` statement bound by the request's `uuid`, verified with a `DB::listen()` collector filtered to `izin` selects. The bound is `<= 1`. This criterion is **blocking** and directly enforces REQ-005.

### 5.5 Document Lifecycle

- **AC-022 (oracle)**: Given a Full Update carrying a file for an Izin that already has a document, Then the new file exists on the private disk, `dokumen` equals the new UUID filename, and the previous file is absent from both the private and legacy `public` disks.
- **AC-023 (oracle)**: Given a Full Update without a file key, Then `dokumen` and its file are unchanged.
- **AC-024 (oracle)**: Given a Full Update carrying an unsupported document type, Then the request-level validation error `The dokumen field must be a file of type: pdf, jpg, jpeg, png.` is raised on the key `dokumen` and the existing document is unchanged.

### 5.6 Approval, Deletion, and Read Paths

- **AC-025 (oracle)**: Given a Jenis Dua-Level Izin at `Diajukan` and the assigned Atasan Pimpinan, When the atasan approval route is posted with `Disetujui`, Then `status` becomes `Disetujui Atasan` and the flash is `Verifikasi atasan berhasil dilakukan`.
- **AC-026 (oracle)**: Given an atasan-approved Jenis Dua-Level Izin and the assigned Pimpinan, When the pimpinan approval route is posted with `Disetujui`, Then `status` becomes `Disetujui` and the flash is `Verifikasi pimpinan berhasil dilakukan`.
- **AC-027 (oracle)**: Given a Jenis Single-Level Izin at `Diajukan`, When `IzinApprovalService::applyAtasan()` is applied with `Disetujui`, Then `status` becomes `Disetujui`. This is pinned at the service seam by `IzinApprovalRaceTest::test_atasan_approval_on_single_level_jenis_is_final`; the HTTP approval route for single-level jenis is covered by AC-025's route-level sibling assertions.
- **AC-028 (oracle)**: Given the owner and an Izin with a document on both disks, When `delete` is called, Then the row is removed, both files are removed, and the flash is `Pengajuan izin berhasil dihapus`.
- **AC-029 (oracle)**: Given `GET izin.{uuid}.dokumen` for a document present only on the legacy `public` disk, Then the response is 200 with `Cache-Control: no-store, private`.
- **AC-030 (oracle)**: Given `GET izin.{uuid}.pdf` for an approved Izin with a Nomor Surat Izin, Then the template selected by `IzinType::pdfTemplate()` is rendered and the download filename is `Surat_Izin_{nama}_{no_surat_izin}.pdf`.

### 5.7 Quality Gates

- **AC-031**: `php artisan test` reports 100% pass with 695 or more assertions.
- **AC-032**: `./vendor/bin/pint --test` reports 0 errors for all changed PHP files.
- **AC-033**: `npx playwright test --project=chromium` reports no new failures.

### 5.8 Error-State and Contract Pins

These criteria were added during remediation. AC-034 and AC-035 close the two critical testability gaps (C8 and C1) and the remainder pin contracts that were previously implicit.

- **AC-034 (C8)**: Given an atasan-approved Izin and a request shaped like the real edit form — `POST izin/{izin}` carrying `_method=PUT`, `_token`, and `no_surat_izin` — When submitted, Then the request **IS** treated as a No-Surat Update: the response redirects to `izin.index` with the `success` flash `Nomor surat izin berhasil diperbarui`, and only `no_surat_izin` changes. This pins the upper end of the three-key contract that AC-019 pins from the other side.
- **AC-035 (C1)**: Given an authenticated actor of any role, When `PUT izin/{unknown-uuid}` is requested, Then the response is **404**, not 403. Resolution failure must precede policy evaluation, per REQ-012 and SEC-005.
- **AC-036 (C1)**: Given an authenticated actor who is neither an admin nor the owner, When `PUT izin/{existing-uuid}` is requested, Then the response is **403**. Together with AC-035 this pins the 404-versus-403 boundary exactly as it behaves today.
- **AC-037 (M4)**: Given a guest, When `POST izin` or `PUT izin/{izin}` is requested, Then the response redirects to the `login` route. This extends the existing guest coverage for `izin.index`.
- **AC-038 (M3 / A4)**: `IzinController.php` still contains the string `Pengajuan izin gagal dihapus.`. The defensive guard is retained deliberately and must not be removed by the refactor.
- **AC-039 (A7)**: Given a successful `store`, Then the persisted `uuid` is the value explicitly assigned by the controller and matches the UUID v4 pattern. The `HasUuids` trait's ordered-UUID generation must not silently take over.
- **AC-040 (A6)**: `IzinController.php` still contains the expression `$izin->no_surat_izin ?? $izin->uuid`. The unreachable fallback is retained deliberately.
- **AC-041 (REQ-011)**: Both `StoreIzinRequest` and `UpdateIzinRequest` consume the shared trait, and neither retains a local `jenis()`, `jenisRules()`, or `messages()` definition. The effective HTTP validation output is unchanged, which is already pinned verbatim by AC-006, AC-011, AC-012, and the PERMA suites.

### 5.9 Create-Form Render Contract

These criteria were added during the consistency-audit remediation (finding MC-1). The three create actions are edited by the plan, yet no test rendered them, leaving the `formData()` view contract unpinned. AC-042 through AC-044 cover the §4.3.1 key contract and AC-045 covers the guard that REQ-011 deduplicates.

- **AC-042 (MC-1)**: Given an authenticated user permitted to create an Izin, When `GET izin/create` is requested, Then the response is 200 with view `izin.create`, and the view data exposes `pegawai`, `jenisIzin` containing every value of `IzinType::all()`, `pimpinanList`, and `atasanList`.
- **AC-043 (MC-1)**: Given the same user, When `GET izin/keluar-kantor/create` is requested, Then the response is 200 with view `izin.create-keluar-kantor` and `jenisIzin` equal to the **scalar** `IzinType::KELUAR_KANTOR`. This pins the deliberate polymorphic override described in §4.3.1.
- **AC-044 (MC-1)**: Given the same user, When `GET izin/tidak-masuk/create` is requested, Then the response is 200 with view `izin.create-tidak-masuk` and `jenisIzin` containing every value of `IzinType::all()`.
- **AC-045 (MC-1)**: Given an authenticated user permitted to create an Izin but with **no linked Pegawai**, When any of `GET izin/create`, `GET izin/keluar-kantor/create`, or `GET izin/tidak-masuk/create` is requested, Then each one redirects to `izin.index` with the `error` flash `Data pegawai tidak ditemukan`. This pins all three guards that REQ-011 collapses into a single helper.

## 6. Test Automation Strategy & Pre-Agreed Test Seams (TDD Blueprint)

### 6.1 Pre-Agreed Test Seams Matrix

| Requirement / Criterion | Test Seam (Public Boundary) | Test Level | Mocking Rules |
| --- | --- | --- | --- |
| REQ-001, §4.1.1, §4.1.2, AC-004 | `IzinSubmissionService::prepareForCreate()`, `prepareForFullUpdate()` | Unit | Real `WorkdayService`, real `IzinType`, real SQLite. **No mocks permitted** — these are pure domain rules. `Carbon::setTestNow()` for date determinism. |
| §4.1.3, AC-008 | `IzinSubmissionService::resolveOwnerUuid()` | Unit | Real `User` and `Pegawai` rows via SQLite. No mocks. |
| REQ-002, AC-005, AC-006, AC-009, AC-011, AC-012 | `POST izin` (`izin.store`) | Feature / Integration | Real DB, real policies. `Storage::fake('local')` when a document is involved. |
| REQ-003, AC-007, AC-010 | `PUT/PATCH izin/{izin}` (`izin.update`) | Feature / Integration | Real DB, real policies. `Storage::fake('local')`, `Storage::fake('public')`. |
| REQ-004, AC-013, AC-014, AC-015 | `GET izin/{izin}/edit` (`izin.edit`) | Feature / Integration | Real DB, real policies. No mocks. |
| AC-016 | `IzinPolicy::update()` | Unit | Real policy instance, real roles and Pegawai rows. No mocks. |
| REQ-005, AC-017, AC-019, AC-020 | `izin.update` + `UpdateIzinRequest` | Feature / Integration | Real DB. No mocks. |
| AC-021 | `izin.update` with a `DB::listen()` query collector | Integration | Real DB. No mocks. |
| REQ-006, AC-022 – AC-024 | `izin.update` + `IzinDocumentService` | Feature / Integration | `Storage::fake()` on both disks. Real service; no service mock. |
| AC-025 – AC-027 | `izin.proses-verifikasi-atasan`, `izin.proses-verifikasi-pimpinan` | Feature / Integration | Real DB, real `IzinApprovalService`. |
| AC-028 | `DELETE izin/{izin}` (`izin.destroy`) | Feature / Integration | `Storage::fake()` on both disks. |
| AC-029 | `GET izin/{uuid}/dokumen` (`izin.dokumen`) | Feature / Integration | `Storage::fake()` on both disks. |
| AC-030 | `GET izin/{uuid}/pdf` (`izin.pdf`) | Feature / Integration | **`PDF` facade mock is allowed and required** — the DomPDF rendering is a third-party boundary. Template name and filename are asserted on the mock. |
| AC-034, AC-036, AC-037 | `izin.update` with real-form-shaped, unauthorized, and guest requests | Feature / Integration | Real DB, real policies. No mocks. |
| AC-035 | `izin.update` with an unknown `{izin}` UUID | Feature / Integration | Real DB, real policies. No mocks. Asserts the 404-before-policy ordering. |
| AC-039 | `POST izin` (`izin.store`) | Feature / Integration | Real DB. No mocks. Asserts the persisted UUID shape. |
| AC-038, AC-040, AC-041 | Source-level assertions over `IzinController.php` and both Request classes | Unit (source inspection) | Reads file contents through reflection; no HTTP, no DB, no mocks. |
| AC-042 – AC-045 | `GET izin/create`, `GET izin/keluar-kantor/create`, `GET izin/tidak-masuk/create` | Feature / Integration | Real DB, real policies. No mocks. Asserts the view name, status, and `formData()` keys, including the scalar `jenisIzin` override. |
| AC-031 – AC-033 | CLI gates | Quality gate | n/a |

### 6.2 Mocking & Isolation Boundaries

- **Real implementation required:** SQLite database and migrations, Eloquent models, `IzinPolicy`, `WorkdayService`, `IzinType`, `IzinSubmissionService`, `IzinApprovalService`, `IzinDocumentService`, `IzinQueryService`, `ApproverDirectoryService`, and the Laravel HTTP request lifecycle.
- **Allowed mocks:** the `PDF` facade (`barryvdh/laravel-dompdf`) at the `izin.pdf` seam, and storage disks via `Storage::fake()`.
- **Prohibited:** mocking `WorkdayService`, `IzinType`, or `IzinSubmissionService` in any test. Mocking internal domain logic would defeat the purpose of the characterization net and is prohibited by `CONSTRAINTS.md §3.4` (no tautological tests).
- **Time:** date-sensitive criteria MUST use `Carbon::setTestNow()` and reset it in `tearDown`.
- **Determinism:** any test whose expectations depend on approval depth MUST pin `jenis_izin` explicitly, because `IzinFactory` randomises it across single-level and two-level values. This rule is recorded in `docs/refactor/legacy-plan-application.md` under "Test Determinism Rules".

### 6.3 Inherited Regression Net (must remain untouched)

| File | Tests |
| --- | --- |
| `tests/Feature/IzinApprovalEndpointsTest.php` | 7 |
| `tests/Feature/IzinApprovalRaceTest.php` | 7 |
| `tests/Feature/IzinCategoryRoutesTest.php` | 2 |
| `tests/Feature/IzinDocumentAuthorizationTest.php` | 4 |
| `tests/Feature/IzinDocumentLifecycleTest.php` | 5 |
| `tests/Feature/IzinLifecycleTest.php` | 8 |
| `tests/Feature/IzinOwnershipAssignmentTest.php` | 4 |
| `tests/Feature/IzinPdfEndpointTest.php` | 5 |
| `tests/Feature/IzinPermaValidationTest.php` | 9 |
| `tests/Feature/IzinValidationRulesTest.php` | 14 |
| `tests/Unit/Policies/IzinPolicyTest.php` | 39 |
| `tests/Unit/Services/IzinApprovalServiceTest.php` | 4 |
| `tests/Unit/Services/IzinDocumentServiceTest.php` | 11 |
| `tests/Unit/Services/IzinQueryServiceTest.php` | 7 |
| `tests/Unit/Support/IzinTypeTest.php` | 5 |
| **Total Izin-scoped** | **139** |

Verified baseline: `php artisan test --filter=Izin` reports 139 passing tests with 347 assertions; the whole suite reports 320 passing tests with 695 assertions.

### 6.4 New Test Files Required by This Specification

| File | Covers |
| --- | --- |
| `tests/Unit/Services/IzinSubmissionServiceTest.php` | AC-004, AC-005, AC-006, AC-007 (normalization contract), AC-008 (attribution contract) |
| `tests/Unit/Policies/IzinEditDecisionEquivalenceTest.php` | AC-016 (proof that the removed `$allowEdit` branch was unreachable) |
| `tests/Feature/IzinNoSuratBoundaryTest.php` | AC-019, AC-020, AC-021, and AC-034 (both ends of the three-key payload contract) |
| `tests/Feature/IzinContractPinTest.php` | AC-035, AC-036, AC-037, AC-039 (404-versus-403 ordering, guest behaviour, persisted UUID shape) |
| `tests/Unit/IzinStructureTest.php` | AC-002, AC-038, AC-040, AC-041 (source-level pins over the controller and both Request classes) |
| `tests/Feature/IzinCreateFormTest.php` | AC-042, AC-043, AC-044, AC-045 (create-form render contract, the scalar `jenisIzin` override, and the deduplicated missing-pegawai guard) |
| `tests/Feature/IzinPermaCapTest.php` | AC-006 and AC-007 — the "no `izin` row is created" and "the stored row is unchanged" halves, added by Phase 1 of `plan-refactor-izin-submission-v1.0.md`. It lives in a refactor-owned file because the inherited `IzinPermaValidationTest` may not be extended (AC-001, CON-006). |

> [!IMPORTANT]
> **No dedicated test for the shared trait.** The trait introduced by REQ-011 has no behaviour of its own — it is a relocation of the PERMA rules. Demanding dedicated coverage for it would produce a test that merely restates the implementation, which `CONSTRAINTS.md §3.4` prohibits as tautological. Its behaviour is pinned **only** at the HTTP seam, by the 23 PERMA tests listed in §6.3.

## 7. Project Structure & Executable Commands

- **Modified file:** `app/Http/Controllers/IzinController.php`
- **Modified file:** `app/Http/Requests/StoreIzinRequest.php` (consumes the shared trait; its own `rules()` stays in place)
- **Modified file:** `app/Http/Requests/UpdateIzinRequest.php` (consumes the shared trait; gains memoization and `resolvedIzin()`; its own `rules()` stays in place)
- **New file:** `app/Services/IzinSubmissionService.php`
- **New file:** `app/Traits/JenisIzinRulesTrait.php` — the shared PERMA rule trait required by REQ-011, declared as `namespace App\Traits;` with the trait name `JenisIzinRulesTrait`. The path and name are **resolved and fixed**, derived from the repository's only trait precedent (`app/Traits/UserTrackingTrait.php`, which uses `namespace App\Traits;` and the `XxxTrait` suffix). See decision D-004; no longer deferred to implementation.
- **New files:** the seven test files listed in §6.4
- **Modified test infrastructure:** `phpunit.xml` — its `<env>` entries become `<server … force="true"/>` so that an inherited shell variable (for example `DB_CONNECTION=mysql`) cannot override the SQLite `:memory:` configuration the security-relevant tests depend on. This is test-isolation hardening rather than a requirement of the refactor, and it is recorded here because audit finding ORPH-01 correctly observed that it appeared in the change set with no requirement behind it.
- **New documentation:** `CONTEXT.md` (root glossary), `docs/adr/0001-izin-no-surat-update-discrimination.md`, `docs/audit/clarification-report-izin-controller-refactor-2026-09-24.md`, `docs/audit/consistency-audit-izin-controller-refactor-2026-09-24.md`, `docs/audit/consistency-audit-izin-controller-refactor-phase3-2026-09-24.md`, and `plan/plan-izin-controller-refactor.md`
- **Untouched production files:** `app/Policies/IzinPolicy.php`, `app/Support/IzinType.php`, `app/Services/IzinApprovalService.php`, `IzinDocumentService.php`, `IzinQueryService.php`, `ApproverDirectoryService.php`, `WorkdayService.php`, `app/Models/Izin.php`, `routes/web.php`, `StoreIzinRequest::rules()`, `UpdateIzinRequest::rules()`, all Verify request classes, and all Blade views.
- **Verification commands** (run in this order; report a failure rather than masking it):

```bash
php artisan test --filter=IzinSubmissionServiceTest
php artisan test --filter=IzinEditDecisionEquivalenceTest
php artisan test --filter=IzinNoSuratBoundaryTest
php artisan test --filter=IzinContractPinTest
php artisan test --filter=IzinStructureTest
php artisan test --filter=IzinCreateFormTest
php artisan test
./vendor/bin/pint --test
npx playwright test --project=chromium
composer audit
npm audit
```

- **No build step is required** for this change (`npm run build` is unaffected because no frontend asset or Blade source changes).

## 8. Implementation Guardrails (Three-Tier System)

- **Always do:** Write the failing test first at the seam named in §6.1; keep `IzinPolicy` as the sole authorization authority; keep every redirect target, flash key, flash message, and validation message byte-identical; run the focused test, then the full suite, then Pint.
- **Ask first:** Adding any new public method outside the three named in §4.1; changing any validation rule, error key, or error message in either Request class; renaming, merging, or retyping a `formData()` key listed in §4.3.1; introducing any new Composer or npm dependency; touching any file listed as "untouched production files" in §7.
- **Never do:** Modify or delete an existing file matching `tests/**/*Izin*`; write a dedicated test for the shared trait; normalize the scalar `jenisIzin` override in `createKeluarKantor()`; add a suppression annotation; skip or delete a test; weaken an assertion; change a route, URL, or HTTP verb; add a migration; write a mass-assignment path that bypasses `validated()`; construct a storage path in the controller.

## 9. Rationale, Context & Architecture Decisions (ADRs)

**Why a service instead of private controller methods.** The PERMA normalization rules are regulatory behaviour that currently exists in two controller branches with slightly different shapes (`store()` recomputes workdays inside the tidak-masuk branch, `update()` compares the already-computed value). Two copies of a capped rule are a drift vector for a compliance requirement. Moving them behind one seam makes the rule testable without HTTP and impossible to fix in only one place.

**Why some dead code is removed and some is retained.** Three branches are unreachable today: `$allowEdit` in `edit()`, the `abort(500)` guard in `destroy()` (assumption A4), and the `?? $izin->uuid` filename fallback in `generatePdf()` (assumption A6). Only `$allowEdit` is removed, because it duplicates an *authorization* decision, and duplicated authorization logic is the classic source of privilege drift. AC-016 exists so that removal is backed by an enumerated proof rather than an argument. The other two are deliberately **kept**: each guards a future model-level veto, and removing them would convert that veto into a silent success. That asymmetry is intentional, and AC-038 and AC-040 hold it in place.

**Why the payload-size heuristic survives — and what the `3` actually means.** `count($this->all()) <= 3` looks arbitrary but is sized for the real edit form: `_token` + `_method` + `no_surat_izin`, exactly as the source comment at `UpdateIzinRequest.php:65-66` states. The clean alternatives — a dedicated route/action, or an explicit client-supplied marker — are behavior changes to the public update contract, and changing them would invalidate the characterization suite that this refactor depends on. Preserving and formalizing the heuristic keeps the refactor independently reviewable. The trade-off is recorded in `docs/adr/0001-izin-no-surat-update-discrimination.md`, and AC-019 plus AC-034 pin both ends of the three-key contract so a future change cannot silently flip the path.

**Why the trait extraction was opened after all.** The first draft of this specification forbade touching the request rule sets, which would have left roughly 50 lines of PERMA rules duplicated verbatim between `StoreIzinRequest` and `UpdateIzinRequest` — directly contradicting the single-source-of-truth goal of the same document. Decision D-002 resolves the contradiction: the identical members are relocated into one trait while each class keeps its own `rules()`. This is an expansion of blast radius, accepted because the duplicated content is the highest-risk part of the domain and is already pinned verbatim at the HTTP seam by 23 tests.

**Why the 404-versus-403 ordering is now specified.** REQ-005 requires a single resolution point for the `Izin` row, which is implemented by memoizing the lookup on the form request. That refactor could silently convert `PUT izin/{unknown-uuid}` from 404 into 403 if `authorize()` consumed a null-safe accessor without preserving the failure mode. The ordering is therefore stated as a contract (REQ-012, SEC-005) and pinned by AC-035 and AC-036, rather than left to the implementer's discretion.

**Why no ADR for the service extraction or the trait extraction.** Both are the established pattern in this codebase (`IzinApprovalService`, `IzinDocumentService`, `IzinQueryService`, `CutiBalanceService`, `CutiEligibilityService`) and follow directly from `CONSTITUTION.md` principle V. Neither is surprising, neither is hard to reverse, and neither had a real alternative — so both fail the ADR triple gate and are recorded here in prose instead.

**Why two earlier repository decisions are deliberately reversed (KC-1, KC-2).** The consistency audit (`docs/audit/consistency-audit-izin-controller-refactor-2026-09-24.md`) found that this specification reverses two decisions already recorded in the repository's history without citing them. Both reversals are intentional and are recorded here so that a future reader who encounters the earlier guidance knows which decision is current and why.

- `docs/superpowers/reports/task-2-review.md:55` recommended **deferring** the trait extraction: *"Two copies tolerable; extract a shared trait only when a third consumer appears."* D-002 extracts it now, with two consumers. The reversal is justified because the duplicated content is the PERMA regulatory rule set that this very document identifies as the highest-value thing to single-source, and it is pinned verbatim at the HTTP seam by 23 tests. The earlier recommendation weighed only duplication cost; it did not weigh the drift risk of a capped regulatory rule.
- `docs/superpowers/reports/final-fix-implementer.md:26` recorded an extra uuid-indexed lookup as *"acceptable"*. AC-021 now removes it. The reversal follows from REQ-012: once resolution is memoized to preserve the 404 contract, a second lookup stops being an acceptable cost and becomes a defect, because a second resolution point is exactly how the 404-versus-403 ordering changes silently.

**Why the trait lives in `app/Traits/` (D-004).** The repository has exactly one trait precedent — `app/Traits/UserTrackingTrait.php`, using `namespace App\Traits;` and the `XxxTrait` suffix — and `AGENTS.md` requires adhering to existing style. The shared rule trait therefore becomes `app/Traits/JenisIzinRulesTrait.php`. The idiomatic Laravel alternative, `app/Http/Requests/Concerns/`, was rejected because introducing a new namespace for a single file is a larger structural change than this refactor's scope justifies. D-004 is trivially reversible and fails the ADR triple gate.
