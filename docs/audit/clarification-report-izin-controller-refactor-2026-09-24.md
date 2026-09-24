# 🔍 TDD Clarification Report [Review Iteration 3]

<!-- markdownlint-disable -->

> [!SUCCESS] REMEDIATION STATUS: RESOLVED
> **Remediated on:** 2026-09-24 by the TDD Specification Architect (`/tdd-spec`).
> **Projected Readiness Score:** 92/100 (Completeness 36/40, Clarity 27/30, Alignment 29/30; no critical veto).
>
> All four Section 1 blockers are resolved. **C8** - AC-034 now pins the real three-entry browser payload (`_token` + `_method` + `no_surat_izin`) as a No-Surat Update, complementing AC-019 which pins the four-entry negative case. **C1** - REQ-012 and SEC-005 state the 404-before-policy ordering contract, pinned by AC-035 (unknown UUID yields 404) and AC-036 (unreachable resource yields 403). **C6** - AC-001 now distinguishes modification from creation, so the new test files required by §6.4 are permitted. **C7** - §6.4 explicitly forbids a dedicated test for the shared trait and cites `CONSTRAINTS.md §3.4`.
>
> Every Section 3 tail item was applied: `final` removed from the service declaration (AC-003), the `resolveOwnerUuid` → `prepareForCreate` ordering promoted to REQ-008, the upload boundary to REQ-009, the explicit UUID override to REQ-010 and AC-039, the delete guard to AC-038, guest behaviour to AC-037, and the trait to REQ-011, AC-041, and §4.3.
>
> Decisions **D-001**, **D-002**, and **D-003** are recorded in spec §1.2. `CONTEXT.md` (11 terms) and `docs/adr/0001-izin-no-surat-update-discrimination.md` were created. D-002 was recorded as prose in spec §9 rather than as an ADR because it fails the triple gate (easy to reverse, unsurprising, established pattern).
>
> Three internal cross-reference defects in the pre-remediation draft were also corrected: the assumption A2 pointer to the equivalence proof (AC-008 → AC-016), the §1.2 pointer to the query-count criterion (AC-016 → AC-021), and the §9 pointer to the no-surat boundary criteria (AC-010 → AC-019 and AC-034).

**Target Document:** `spec/spec-feature-izin-controller-refactor.md`
**Readiness Score:** 75/100 _(as audited before remediation; superseded by the remediation block above)_
**Status:** Below Threshold (< 80) _(pre-remediation)_

**Score Breakdown (Quality Gate Rubric):**

- **Completeness (max 40):** 28 - The submission-rule surface is fully specified and every criterion maps to a named seam, but error-state contracts remain missing: C1 (404 versus 403 ordering), C8 (real browser payload shape), and M4 (guest behaviour on `store`/`update`).
- **Clarity & Testability (max 30):** 24 - All three open decisions (D-001, D-002, D-003) are resolved and each criterion names a concrete seam, but C6 (ambiguous AC-001 wording) and C7 (missing anti-tautology instruction) remain.
- **Alignment & Constraints (max 30):** 23 - Fully traceable to `docs/refactor/legacy-plan-application.md` and `discovery-draft.md`; however §1.1 still contradicted D-002 pending amendment, and `CONTEXT.md` and the ADR did not exist.
- **Critical Flaw Veto:** Triggered (Max 79) - C1 and C8 are blocking testability gaps.

---

## 1. 🚨 Critical Findings (Untestable Blockers)

- **Requirement / Section:** §4.2 / REQ-005 - *"the request payload contains at most 3 entries (`count($this->all()) <= 3`)"* and *"the resolved `Izin` instance is reused by `authorize()`, `rules()`, and the controller action"*
  - **Testability Gap:** No criterion pins the **real browser payload shape** (`_token` + `_method` + `no_surat_izin`). The suite only pins a synthetic `put()` call carrying a single key, so the `<= 3` threshold could be tightened to `<= 1` with the entire 320-test suite still passing while the production no-surat path breaks. The upper boundary (4 or more keys) is also unpinned.
  - **Proposed Seam:** Integration `POST izin/{izin}` carrying `_method=PUT`, `_token`, and `no_surat_izin` must be treated as a No-Surat Update; a payload with one additional key (4 total) must not.

- **Requirement / Section:** REQ-005 - *"MUST be evaluated at most once per request"* (single resolution point)
  - **Testability Gap:** The specification requires a single resolution point but never states the **404 versus 403 ordering contract**. `UpdateIzinRequest.php:14` uses `firstOrFail()` so an unknown UUID yields **404**, while `isNoSuratUpdate()` uses `first()`. If `resolvedIzin()` is consumed by `authorize()` without preserving the 404, `PUT izin/{unknown-uuid}` silently becomes **403**, and no existing test fails. No criterion covers this case at all.
  - **Proposed Seam:** Integration `PUT izin/{unknown-uuid}` by any authenticated user (including a non-admin) must return **404**, not 403; `PUT izin/{other-users-uuid}` must return **403**. Resolution must happen before policy evaluation.

- **Requirement / Section:** AC-001 - *"No file matching `tests/**/*Izin*` is modified"*
  - **Testability Gap:** The wording conflates *modified* and *created*. After D-002 and D-003 the specification mandates **three new test files** (§6.4), and all three match `tests/**/*Izin*`. The criterion cannot be verified deterministically as written.
  - **Proposed Seam:** Reword to: no **pre-existing** Izin test file may be modified or deleted; verified with `git status --short tests/` showing no `M` or `D` status for an Izin test file.

- **Requirement / Section:** §6.4 (new test files) against `CONSTRAINTS.md §3.4` (no tautological tests)
  - **Testability Gap:** The trait produced by D-002 has no behaviour of its own; demanding dedicated coverage for it would produce a test that merely restates the implementation, violating the project floor-guard. The specification does not say this, so a dev agent is likely to add one in the name of coverage.
  - **Proposed Seam:** No new test seam for the trait. State explicitly in §6.4 that the trait's behaviour is pinned **only** at the HTTP seam by the 23 PERMA tests (`IzinValidationRulesTest` 14 plus `IzinPermaValidationTest` 9).

## 2. 🧩 Resolved Items & Pre-Agreed Boundaries

- **Original Ambiguity:** *"CLARIFICATION NEEDED - no-surat discrimination strategy"* (§1.2)
  - **Resolution & Boundary:** **D-001** - the `count($this->all()) <= 3` heuristic is retained, formalized, and memoized. The rationale is corrected: `3 = _token + _method + no_surat_izin`, evidenced by the code comment at `UpdateIzinRequest.php:65-66` (`csrf, method, and no_surat_izin only`). A dedicated route and an explicit marker field were both rejected because they change the public update contract and invalidate the characterization net. Recorded as ADR-0001 (meets the triple gate).
  - **Target Seam:** Integration `POST/PUT izin/{izin}` (`izin.update`)

- **Original Ambiguity:** §1.1 - *"No changes to validation rule sets... keep their current `rules()`, `messages()`, and `jenisRules()` exactly"* versus the specification's stated goal of a single source of truth
  - **Resolution & Boundary:** **D-002** - the byte-identical `jenis()` (`StoreIzinRequest.php:86-91` / `UpdateIzinRequest.php:109-114`), `jenisRules()` (61-84 / 84-107), and `messages()` (34-53 / 43-62) are extracted into one shared trait; `rules()` stays per class because the two genuinely differ. Related **M5**: the three identical `Data pegawai tidak ditemukan` guards (`IzinController.php:46-48`, `305-307`, `324-326`) collapse into one private helper.
  - **Target Seam:** Integration `POST izin` and `PUT izin/{izin}` - the PERMA messages and error keys are already pinned verbatim

- **Original Ambiguity:** *"CLARIFICATION NEEDED - query-count target"* (§1.2)
  - **Resolution & Boundary:** **D-003** - the `<= 1` bound becomes a **blocking** criterion, tested with `DB::listen()` filtered to `select` statements against `izin` bound by `uuid`, asserting `<= 1`. Memoization is already mandatory for C1, so the reduction from 4 lookups to 1 follows naturally.
  - **Target Seam:** Integration `PUT izin/{izin}` with a query collector

- **Resolved fact:** Three unreachable branches were inventoried - `$allowEdit` in `edit()`, `abort(500)` in `destroy()`, and the `?? $izin->uuid` fallback in `generatePdf()` (line 279). All three are now catalogued as pinned quirks.
  - **Target Seam:** Unit `IzinPolicy::update()`; Integration `GET izin/{izin}/edit`, `DELETE izin/{izin}`, `GET izin/{uuid}/pdf`

## 3. ⚠️ Assumed / Auto-Resolved / Out of Scope (The 20% Tail)

- **`final class` on `IzinSubmissionService`** (§4.1) deviated from repository convention
  - **Handling:** `[Assumed / Auto-Resolved]` - declared as `class`, consistent with `IzinApprovalService`, `IzinDocumentService`, `IzinQueryService`, and `WorkdayService`.
- **Ordering of `resolveOwnerUuid()` and `prepareForCreate()`** was implicit only
  - **Handling:** `[Assumed / Auto-Resolved]` - `resolveOwnerUuid()` must run first so `prepareForCreate()` receives a final `pegawai_uuid`.
- **Upload orchestration boundary** was unstated
  - **Handling:** `[Assumed / Auto-Resolved]` - `storeDocument()` stays owned by `IzinDocumentService` and is called from the controller; it must not move into `IzinSubmissionService`.
- **Fate of `$validated['uuid'] = Str::uuid()`** (`IzinController.php:97`) was unstated, and `Izin` uses `HasUuids` (ordered UUID) while the controller overrides it with a random v4
  - **Handling:** `[Assumed / Auto-Resolved]` - retain the explicit override so the stored `uuid` value stays identical.
- **No criterion guarded `abort(500, 'Pengajuan izin gagal dihapus.')`** although §1.2 A4 decided to keep it
  - **Handling:** `[Assumed / Auto-Resolved]` - a structural criterion now requires that message string to remain in `IzinController.php`.
- **Guest behaviour on `izin.store` and `izin.update`** was unpinned (`RouteMiddlewareTest` only covers `izin.index`)
  - **Handling:** `[Assumed / Auto-Resolved]` - a criterion now requires a 302 redirect to `login` for guests on both routes.
- **Trait risked a tautological test** (C7)
  - **Handling:** `[Assumed / Auto-Resolved]` - stated explicitly in §6.4; no dedicated trait test.
- **`CONTEXT.md` (11 terms) and `docs/adr/0001-...md`** did not exist
  - **Handling:** `[Assumed / Out of Scope]` - created as part of the remediation; no `CONTEXT-MAP.md` exists, so the glossary lives at the repository root.

## 4. 📝 Next Steps

- Amended by the authoring agent (`/tdd-spec`): §1.1 (deduplication opened), §1.2 (decisions recorded), §2.1/§2.2 (glossary and ADR created), §3.1 (new requirements), §4.1 (`final` removed), §4.2 (payload-shape and 404-order contracts), §4.3 (structural assertions), §5.1 (AC-001 reworded), §5.4 (AC-021 made blocking), new §5.8 (contract pins), §6.1/§6.4 (matrix and test files), §7 (file inventory), §9 (rationale).
- `docs/adr/0001-izin-no-surat-update-discrimination.md` written for D-001.
- `CONTEXT.md` written at the repository root with the 11 resolved domain terms.
- D-002 did **not** meet the triple gate and is recorded as prose in §9 rather than as an ADR.

---

> **User Decision Prompt:** (Iteration >= 3 - shown although the score is below the threshold)
> The document reached a Readiness Score of **75/100**. The remaining blockers are no longer open decisions: D-001, D-002, and D-003 are all resolved and the rest are mechanical corrections that the authoring agent can execute directly. Do you want to **PROCEED** (hand remediation to `/tdd-spec`, then move to `/tdd-plan-tasks`) or **REFINE** (continue interrogating before remediation)?
