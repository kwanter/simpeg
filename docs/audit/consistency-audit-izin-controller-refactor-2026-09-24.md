<!-- markdownlint-disable -->
# 🔍 Consistency & Traceability Audit Report [Review Iteration 1]

> [!SUCCESS] REMEDIATION STATUS: RESOLVED
> **Remediated on:** 2026-09-24 by the TDD Specification Architect (`/tdd-spec`).
> **Projected Readiness Score:** 94/100 (Completeness 37/40, Clarity 28/30, Alignment 29/30; no critical veto).
>
> **MC-1 closed.** Spec §4.3.1 now states the view data contract as a table of keys, types, and Blade consumers, including a `[!WARNING]` that `jenisIzin` is deliberately a scalar on the keluar-kantor path. Four criteria were added: **AC-042** (`izin/create` renders with every `IzinType::all()` value plus the approver lists), **AC-043** (`create-keluar-kantor` exposes the scalar override), **AC-044** (`create-tidak-masuk` exposes the full list), and **AC-045** (all three routes redirect to `izin.index` with the `Data pegawai tidak ditemukan` flash when the actor has no linked Pegawai — pinning the guard that REQ-011 deduplicates). §6.1 gained a seam row, §6.4 gained `tests/Feature/IzinCreateFormTest.php`, and §7 now counts six new test files.
>
> **MC-2 closed.** Decision **D-004** resolves the trait to `app/Traits/JenisIzinRulesTrait.php` with `namespace App\Traits;`, derived from the repository's only trait precedent (`app/Traits/UserTrackingTrait.php`, `XxxTrait` suffix). Spec §7 no longer defers the path, so the file inventory is fully auditable today.
>
> **KC-1 and KC-2 closed.** Spec §9 now cites `docs/superpowers/reports/task-2-review.md:55` and `docs/superpowers/reports/final-fix-implementer.md:26` and states both reversals explicitly, with the reasoning that differs from the earlier guidance (drift risk of a capped regulatory rule; the second lookup being the mechanism by which the ordering regresses). The trait-location rationale for D-004 is also recorded there.
>
> §8 guardrails were extended so the two implicit contracts cannot be "tidied away": `formData()` key changes are now ask-first, and normalizing the scalar `jenisIzin` override is now never-do.
>
> Remaining unactioned item: the Plan-side updates in section 4 of this report (a new ticket or extended Ticket-009 for the create-form renders, plus the guardrail names). Those belong to `/tdd-plan-tasks`, not to this specification.

> [!SUCCESS] REMEDIATION STATUS: RESOLVED (Plan)
> **Remediated on:** 2026-09-24 by the TDD Planner Architect (`/tdd-plan-tasks`). Plan revised to **v1.1** by surgical edit; all eleven original tickets, their dependencies, and their commit references were preserved, and ticket numbering is append-only.
> **Projected Readiness Score:** 94/100 (Completeness 37/40, Clarity 28/30, Alignment 29/30; no critical veto).
>
> **MC-1 closed on the plan side.** A new **Ticket-012** ("Pin the create-form render contract", Size S) was added to **Phase 1**, not to a later phase, so the create routes are pinned *before* Ticket-009 edits all three of their actions. It carries RED, a mandatory RED-proof step (temporarily replace the `createKeluarKantor()` override and confirm AC-043 fails), GREEN, VERIFY, and COMMIT, and it covers AC-042, AC-043, AC-044, and AC-045 at `tests/Feature/IzinCreateFormTest.php`. The ordering constraint is stated in three places: the dependency graph edge `T12 --> T9`, `ASSUMPTION-011`, and `RBCK-007`.
>
> **MC-2 closed on the plan side.** Ticket-003 step 0 changed from "resolve the trait convention" to "confirm the path fixed by spec decision D-004" (`app/Traits/JenisIzinRulesTrait.php`, `namespace App\Traits;`), which is no longer a deferred guess.
>
> **Implicit contracts named.** Ticket-005 and Ticket-009 each carry a "Guardrails (do not break)" bullet naming the `formData()` key and type contract from Spec §4.3.1 and the deliberate scalar `jenisIzin` override. Ticket-009 step 3 now runs `--filter='IzinStructureTest|IzinCreateFormTest|IzinLifecycleTest'`.
>
> **Traceability refreshed.** New §2.4 records 45 of 45 acceptance criteria mapped; `ASSUMPTION-003` is marked resolved; `ASSUMPTION-011` and `ASSUMPTION-012` were added; Ticket-011 gained a View Contract Gate step and its acceptance record now spans AC-001 through AC-045; the Floor-Guard gate was updated from 41 to 45 criteria; and `RBCK-008` was added.
>
> Plan-side validation after revision: 12 tickets (1 M, 8 S, 3 XS — no L/XL), one VERIFY and one APPROVAL checkpoint per phase, 45 of 45 acceptance criteria referenced, no dangling specification references.
>
> **No items remain unactioned from section 4 of this report.**

**Readiness Score:** 79/100 _(as audited before remediation; superseded by the remediation block above)_
**Status:** Below Threshold _(pre-remediation)_
**Upstream Artifacts:**
- [ ] PRD: `docs/prd/{feature}.md` — **absent**; the PRD phase was bypassed by documented agreement (`ASSUMPTION-001`). Substitute brief: `discovery-draft.md` and `docs/refactor/legacy-plan-application.md`.
- [x] Spec: `spec/spec-feature-izin-controller-refactor.md`
- [x] Plan: `plan/plan-izin-controller-refactor.md`
- [x] Glossary: `CONTEXT.md`
- [x] ADR: `docs/adr/0001-izin-no-surat-update-discrimination.md`

**Audit limitation:** because no PRD exists, the PRD → Spec link cannot be audited. It was replaced with a Spec → Plan traceability audit plus a consistency check against the repository's architectural memory (`docs/superpowers/reports/`, `.hermes/`, `.sisyphus/`).

---

## 1. 📊 Score Breakdown (Quality Gate 40/30/30)

- **Completeness (max 40):** 30/40 - All 41 acceptance criteria and all 13 requirements are mapped to tickets, but three controller actions edited by the plan have no test coverage at all, and the Spec §7 file inventory is not yet complete (the trait path was unresolved).
- **Clarity (max 30):** 26/30 - Every ticket carries RED/GREEN/VERIFY/COMMIT with exact commands, filters, verbatim messages, and revert guards. Two implicit contracts were unnamed.
- **Alignment (max 30):** 23/30 - Terminology matches `CONTEXT.md` and the ADR follows the standard template, but two architectural decisions already recorded in the repository are reversed without citing them.
- **Critical Flaw Veto:** **Yes** - MC-1 is a blocking testability gap on the exact paths the plan edits, capping the score at 79.

---

## 2. 🔍 Traceability & Blast Radius Findings

### 🚨 Critical Blockers (Must Fix to reach >= 80)

- **Missing Coverage (Upstream ➔ Downstream): MC-1 - three actions edited by the plan have zero coverage**
  - **Item:** Ticket-009, and Ticket-005 indirectly via the adjacency of `store()` and `create()`.
  - **Gap:** Ticket-009 edits `create()`, `createKeluarKantor()`, and `createTidakMasuk()`. A search across `tests/` found **zero** references to `izin.create`, `izin.create-keluar-kantor`, or `izin.create-tidak-masuk`; the only exercised izin route is `izin.store` (POST). The `formData()` payload feeding four Blade views is therefore pinned by no test, even though Ticket-009 edits all three of its callers.
  - Evidence of the unpinned view contract:

    | Blade view | `formData()` keys consumed |
    | --- | --- |
    | `resources/views/izin/create.blade.php:32,46,59` | `$jenisIzin`, `$atasanList`, `$pimpinanList` |
    | `resources/views/izin/create-tidak-masuk.blade.php:61,74` | `$atasanList`, `$pimpinanList` |
    | `resources/views/izin/create-keluar-kantor.blade.php:33,39,52` | `$jenisIzin` (**scalar**, not a list), `$atasanList` |
    | `resources/views/izin/edit.blade.php:60,74` | `$jenisIzin`, `$atasanList` |

  - **Remediation:** the Spec must add criteria for the render contract of the three `create` routes and the `formData()` keys, and the Plan must add a small ticket pinning those renders. Without this, a refactor whose entire justification is its safety net has a hole exactly where its tickets operate.

- **Missing Coverage (Upstream ➔ Downstream): MC-2 - Spec §7 file inventory not verifiable**
  - **Item:** Spec §7, line: "New file: the shared PERMA rule trait required by REQ-011 ... verify against the repository's existing trait rather than assuming `App\Http\Requests\Concerns`."
  - **Gap:** the path was deliberately deferred to implementation, so the §7 inventory was incomplete and unauditable. Ticket-003 step 0 mandates the verification, but no step mandates writing the resolved path back into the Spec.
  - **Remediation:** resolve the path in the Spec, or add a step requiring it be recorded after Ticket-003 step 0.

### ⚠️ Cross-Document Contradictions (repository architectural memory)

- **KC-1 - prior recommendation to defer the trait extraction**
  - `docs/superpowers/reports/task-2-review.md:55` records: *"~60 duplicated lines (`jenisRules`/`jenis`/`messages`). Two copies tolerable; **extract a shared trait only when a third consumer appears**."*
  - D-002 extracts it now, with two consumers. The justification in Spec §9 is sound (a single PERMA source of truth, pinned by 23 verbatim tests), but it does not cite the recommendation it reverses, leaving two contradictory pieces of guidance for the next reader.

- **KC-2 - prior decision that an extra lookup was "acceptable"**
  - `docs/superpowers/reports/final-fix-implementer.md:26` records: *"Cost: one extra uuid-indexed lookup inside the predicate - **acceptable**."*
  - AC-021 now mandates `<= 1` lookup, which removes exactly that accepted cost. This is a legitimate evolution, but it contradicts a recorded decision without citing it.
  - **Remediation for KC-1/KC-2:** one paragraph in Spec §9 naming both artifacts and stating the reversal explicitly.

### ✅ Oracle Claims Successfully Verified

- **AC-035 (404 versus 403):** `task-2-review.md` finding 7 previously recorded *"Nonexistent uuid on update now yields validation errors ... instead of 404"*. `UpdateIzinRequest.php:14` now uses `firstOrFail()`, so the AC-035 premise holds today (404 for an unknown UUID, 403 for an existing unauthorized record), and Ticket-002 step 1b mutates precisely that risk.
- **AC-017 and AC-018 (no-surat):** `final-fix-implementer.md:16` states the latent `route('uuid')` bug (the actual parameter is `{izin}`) had just been fixed and that the no-surat path was *"covered implicitly ... no dedicated no-surat test existed before or after"*. Two tests now genuinely pin it: `IzinLifecycleTest::test_admin_no_surat_update_changes_only_the_document_number` and `IzinValidationRulesTest::test_no_surat_update_rejects_duplicate_number`. The `(oracle)` claim in AC-017 and AC-018 is valid.
- **Blast radius confined:** a search for `IzinController` outside documentation returned only `routes/web.php` and a tree listing in `README.md`. No console command, seeder, `cronjob/` script, or provider references it. `$allowEdit` appears only at `IzinController.php:60,64`. The guard string `Data pegawai tidak ditemukan` appears 3 times in `IzinController` (and 4 more times in `CutiController`, which is out of scope - Ticket-009's assertion is correctly scoped to `IzinController.php`).

### ⚠️ Minor Gaps (Assumed / Backlog - The 20% we defer)

- **Structural pins are coupled to source text** - AC-002, AC-038, AC-040, and AC-041 produce five assertions that read file contents. Reformatting or reflowing comments could redden them with no behaviour change.
  - **Handling:** `[Assumed / Backlog]` - intentional as structural pins; a comment in the test is enough to stop a future reader treating them as false positives.
- **`$jenisIzin` has two types** - `createKeluarKantor()` replaces it from a list to a string (`IzinController.php:310`), and that view treats it as a scalar.
  - **Handling:** `[Assumed / Backlog]` - worth naming in the Ticket-005/009 guardrails so a "consistency tidy-up" does not delete the override.
- **The `DB::listen()` technique is new to this repository** (AC-021).
  - **Handling:** `[Assumed / Backlog]` - already flagged as `ASSUMPTION-006` in the Spec and Plan, with the mitigation "adjust the filter, never the assertion bound".

### 🧹 Orphaned Items (Scope Creep)

**None.** All eleven tickets trace to a REQ or AC in the Spec. The mutation-proof steps in T-001, T-002, and T-010 are a verification technique rather than new requirements, and are guarded by the requirement that `git diff app/` is empty afterwards.

---

## 3. 🛡️ Standards & Testability Audit

- **ADR Format Compliance:** **PASS** - `docs/adr/0001-izin-no-surat-update-discrimination.md` satisfies the template (`# NNNN - title`, `Date`, `Status`, Context / Decision / Consequences plus Considered Options). Triple gate passes: hard to reverse, surprising, real trade-off. Skipping an ADR for D-002 is also gate-correct and documented in Spec §9.
- **Domain Glossary Alignment:** **PASS** - `CONTEXT.md` holds 11 terms, each with `_Avoid_:` in the exact syntax required by `CONTEXT-FORMAT.md`, with no duplicate entries or acronym twins.
- **Codebase Reality & Test Seams:** **FAIL** - the declared seams are correct and mock prohibitions are explicit, but the "comprehensive safety net" claim does not hold for the three actions the plan edits (MC-1), and the Spec §7 seam inventory was not final (MC-2).

---

## 4. 📝 Action Plan (Corrective Actions)

- [ ] **PRD Updates:** None (the phase was bypassed; there is no PRD to update).
- [ ] **Spec Updates:**
  - Add criteria for the render contract of `GET izin/create`, `GET izin/keluar-kantor/create`, and `GET izin/create-tidak-masuk`, plus the `formData()` keys (`pegawai`, `jenisIzin` as list-or-scalar, `pimpinanList`, `atasanList`) - closes MC-1.
  - Resolve the trait path in §7, or mandate recording it after Ticket-003 step 0 - closes MC-2.
  - Add a §9 paragraph citing `docs/superpowers/reports/task-2-review.md:55` and `docs/superpowers/reports/final-fix-implementer.md:26` and stating the reversal explicitly - closes KC-1 and KC-2.
- [ ] **Plan Updates:**
  - Add one XS ticket to pin the three `create` route renders, or extend Ticket-009 with render assertions.
  - Add a Ticket-003 sub-step recording the final trait path back into Spec §7.
  - Name the two implicit contracts in the Ticket-005/009 guardrails: the `formData()` keys and the scalar `$jenisIzin` on the keluar-kantor path.

After remediation the projected score is **>= 88** and the plan is ready for `/tdd-write-code`.
