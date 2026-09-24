# Task 1 Review: IzinApprovalService (race-safe approval)

Reviewer scope: task gate against `docs/superpowers/plans/2026-08-22-simpeg-arch-remediation.md` Task 1 + Global Constraints + Reference. Diff base 98e6098 → head 54b49af, read from `docs/superpowers/reports/task-1.diff` (3 files, +193/−24). Read-only review; tests not re-run per review constraints — test results below are implementer claims, flagged as such.

## SPEC: PASS

| Task 1 step | Verdict | Evidence |
|---|---|---|
| 1. Write `tests/Feature/IzinApprovalRaceTest.php` modeled on cuti duplicate-approval test, cover (a)–(d) | PASS | 7 tests: (a) two-level → `Disetujui Atasan`, single-level → `Disetujui`; (b) second `applyAtasan` throws ValidationException **and** asserts persisted state unchanged; (c) `applyPimpinan` on `Diajukan` throws, on `Disetujui Atasan` → `Disetujui`; (d) both reject paths. Service-level, real DB (verified `tests/SimpegTestCase.php:14` uses RefreshDatabase; `database/factories/IzinFactory.php` exists). |
| 2. RED run (service missing) | PASS (claimed) | Report shows class-not-found failure for all 7. Not re-runnable here. |
| 3. Create `app/Services/IzinApprovalService.php` copying CutiApprovalService structure | PASS | `DB::transaction` + `lockAtStatus` (`where('uuid')->lockForUpdate()->firstOrFail()`) + expected-status guard (`Diajukan` / `Disetujui Atasan`) + `ValidationException::withMessages` with cuti-mirroring message. Constants `APPROVE='Disetujui'`, `REJECT='Ditolak'`. `tanggal_verifikasi_*` = `Carbon::now()` (matches cuti). No balance logic. Two disclosed deviations, both accepted (below). |
| 4. New test green | PASS (claimed) | 7 passed / 17 assertions per report. |
| 5. Rewire controller verify methods, keep `authorize()` | PASS | Both methods delegate to service; `$request->validate` blocks, authorize calls, redirects, flash messages untouched (hunks are strictly interior). `Auth` import pre-exists (IzinController.php:14); `IzinType` and `ValidationException` imports still used elsewhere (verified). |
| 6. Full suite green | PASS (claimed) | 252 passed / 497 assertions (245 baseline + 7 new). |
| 7. Commit `fix(izin): race-safe approval service (port SEC-012 pattern)` | PASS | Head 54b49af carries that message per task metadata. |

Scope: exactly the 3 planned files; Tasks 2–5 untouched. Nothing more, nothing less.

### Accepted deviations (disclosed by implementer, verified by reviewer)

1. **Approver uuid columns not written.** Plan: "approver uuid column if the izin table has one (check migration; if none, skip)". Izin has `atasan_pimpinan_uuid`/`pimpinan_uuid`, but they are **submission-required assignment columns** (`IzinController.php:138-139` `required|exists:pegawai,uuid`) consumed by `IzinPolicy.php:24,29` for verify-authorization and by `IzinQueryService.php:41,45` for list scoping; the pre-fix controller never wrote them at approval. Izin has no cuti-`verifikator_uuid`-style who-approved column. Skipping is behavior-preserving and correct — overwriting would reassign authorization data when an admin approves on behalf. See Finding 2 for a correction to the report's rationale.
2. **`?Pegawai` nullable instead of plan's `Pegawai`.** Mirrors cuti's `applyVerifikator(..., ?Pegawai $verifikator = null)`. Prevents a 500 when a user without a pegawai row reaches the method. Benign — the param is currently inert (Finding 1).

Global constraints: statuses written (`Disetujui`, `Disetujui Atasan`, `Ditolak Atasan`, `Ditolak`) all in the enum set; no new deps; no framework change; no files outside Task 1 touched. Indonesian strings preserved.

## QUALITY: APPROVED

### Race-safety of the ported pattern — correct

- Transaction wraps lock + guard + mutate + save; exception inside the closure rolls back (and is thrown before any mutation anyway).
- Lock re-fetches by `uuid` with `lockForUpdate()`, discarding the stale in-memory model — second concurrent caller blocks on the row lock, then fails the expected-status guard. This is the SEC-012 fix, structurally identical to `CutiApprovalService::lockAtStatus` (CutiApprovalService.php:80-90).
- `applyPimpinan` now hard-requires `'Disetujui Atasan'` where the old inline code had **no precondition at all** — closes the skipped-atanan hole at the data layer (policy previously the only guard). Strict improvement, consistent with `IzinPolicy`'s `verifikasi_atasan == 'Disetujui'` gate.
- Double-submit now surfaces as redirect-with-errors instead of silently overwriting status — intended behavior change.

### Consistency with CutiApprovalService — faithful

Same namespace/layout, same constants, same `Carbon::now()`, same lock helper, same exception message shape ("Permohonan izin …" vs "Permohonan cuti …"). Balance logic correctly omitted (Izin has none). Deliberate duplication over a shared abstraction matches the plan's stated architecture decision.

### Test quality — real behavior

Real DB via RefreshDatabase (verified in base class), factories not mocks, and the duplicate-approval test re-reads from DB inside the catch to prove state survived — the exact regression SEC-012 fixed. Coverage maps 1:1 to plan scenarios (a)–(d).

### Findings

1. **[minor]** `app/Services/IzinApprovalService.php:18,37` + `app/Http/Controllers/IzinController.php` (verify methods) — `?Pegawai $atasan`/`?Pegawai $pimpinan` are accepted but never read; the controller runs `Pegawai::where('nip', Auth::user()->nip)->first()` per approval and discards the result. One wasted query and a misleading signature (cuti uses the same param to write uuid columns; izin has no target column). Fix: drop the params + the two resolutions (amend plan interface), or keep them with a one-line comment pointing at a future who-approved audit column. Plan-mandated interface is why it shipped; debt, not a bug.
2. **[minor]** `docs/superpowers/reports/task-1-implementer.md` (self-review, approver-column rationale) — report claims cuti's uuid columns are "null until approval, then written". True only of `verifikator_uuid` (CutiApprovalService.php:28-30). Cuti's `pimpinan_uuid`/`atasan_pimpinan_uuid` are ALSO submission-required (StoreCutiRequest.php:25-26) AND overwritten at approval (CutiApprovalService.php:45,66), then used by CutiPolicy for view/verify gating. The skip decision for izin remains correct (izin's columns carry assignment semantics the pre-fix code never touched; overwriting would change IzinPolicy/IzinQueryService behavior), but the rationale as written would mislead a future port. Fix: correct the report; no code change.
3. **[note]** No HTTP-level test exercises the rewired controller methods (service-level only). Pre-existing gap — no HTTP test existed for these endpoints before, and the plan scoped tests to the cuti-regression-test level. The ValidationException → redirect-back path is untested. Add an HTTP test if/when Task 2 touches the same methods.
4. **[note]** RED/GREEN/full-suite outputs are implementer claims; this review was barred from running tests. Structural preconditions for the claims verified: RefreshDatabase in `SimpegTestCase`, `IzinFactory` exists, `Auth`/`IzinType`/`ValidationException` imports all still live in the controller.
5. **[note]** `lockAtStatus` is a verbatim duplicate of cuti's private helper — intentional per plan ("port the pattern instead of inventing a shared abstraction"; plan explicitly defers a shared engine until a third workflow module). Consistent, not drift.

### Focused checks performed (one per named risk)

- Risk: missing `Auth` import after rewiring → 500 on every approval (HTTP path untested). Check: read IzinController head — `use Illuminate\Support\Facades\Auth;` at line 14. Cleared. Also confirmed `IzinType` and `ValidationException` imports still referenced (lines 119+, 167, 252).
- Risk: tests not isolated / factory missing → claimed green impossible. Check: `tests/SimpegTestCase.php` uses RefreshDatabase; `database/factories/IzinFactory.php` exists. Cleared.
- Risk: implementer's approver-column claim wrong → silent spec miss. Check: grep `atasan_pimpinan_uuid|pimpinan_uuid` across app/ — confirmed submission-required (IzinController:138-139), policy-consumed (IzinPolicy:24,29), query-scoped (IzinQueryService:41,45), never written by pre-fix approval code; cuti writes its equivalents at approval (CutiApprovalService:45,66). Decision upheld, rationale corrected (Finding 2).
- Risk: drift from the proven cuti pattern. Check: full read of `app/Services/CutiApprovalService.php` — structural match confirmed (see Consistency).

## Verdict

- **SPEC: PASS** — all Task 1 steps implemented exactly; two disclosed, verified, accepted deviations; no scope creep.
- **QUALITY: APPROVED** — race-safe port is structurally correct and behavior-preserving; tests exercise real DB state transitions; two minor findings are documentation/debt, neither blocks.
