# Task 2 Review — Izin FormRequests

Reviewer gate: spec compliance first, then code quality.
Base 54b49af → Head a4c02fa. Diff: docs/superpowers/reports/task-2.diff (486 lines, read once in full).
Outside-diff checks (named risks, one focused read each):
1. `app/Http/Controllers/IzinController.php:141-184` — update() hunk cut off mid-function; needed the controller's own `$isNoSuratUpdate` computation to judge divergence risk (named risk: isNoSuratUpdate re-fetch).
2. `app/Http/Requests/StoreCutiRequest.php` — pattern-consistency verdict duty (named risk: Cuti request pattern).

No git commands run, no tests run, no tree mutation.

## SPEC COMPLIANCE — PASS

| Step | Verdict | Evidence |
|---|---|---|
| Move rules verbatim into 4 request classes, `authorize(): true`, policy stays in controller | PASS | Key-by-key comparison below. All four classes return `true` with policy-in-controller comment. `$this->authorize()` calls retained in controller bodies (visible in diff context for update/verifyAtasan/verifyPimpinan; store's `authorize('create')` also retained). |
| `php artisan test --filter IzinPermaValidationTest` green | PASS (claim) | Report: 9 passed, 21 assertions. Not re-run (review is read-only, no test runs). |
| Swap signatures; delete the two private validate methods | PASS | `validateIzinKeluarKantor`/`validateIzinTidakMasukKerja` deleted; `store(StoreIzinRequest)`, `update(UpdateIzinRequest)`, both verify methods typed. |
| `php artisan test` green | PASS (claim) | Report: 252 passed, 497 assertions. Not re-run. |
| Commit `refactor(izin): extract FormRequests from inline validation` | PASS (claim) | Head a4c02fa matches session record of the Task 2 commit. Message not verified (no git commands allowed). |

Test claims accepted per report; flagged unverifiable under this review's constraints.

### Rule-drift verification (verbatim-move claim)

- **Store base (10 keys)**: removed inline vs `StoreIzinRequest::rules()` — identical constraints; only notation pipe-string → array (semantics-preserving, matches Cuti style). ✓
- **Update full (9 keys)**: = store minus `pegawai_uuid`, as removed. ✓
- **no_surat branch**: `Rule::unique('izin','no_surat_izin')->ignore($izin->uuid,'uuid')` → `->ignore($this->route('uuid'),'uuid')`. Controller fetched `$izin` by the same route uuid (`where('uuid', $uuid)->firstOrFail()`), so identical ignore value. ✓
- **Keluar-kantor overlay (5 keys + 3 messages)**: identical, including `date_equals:'.now()->toDateString()` and `after:jam_mulai`. ✓
- **Tidak-masuk overlay (3 keys + 1 message)**: identical. ✓
- **Verify atasan/pimpinan (2 keys each)**: identical (`required|in:Disetujui,Ditolak`, `nullable|string`). ✓

**array_merge semantics** (string keys → overlay replaces base key wholesale): verified accept-set equivalence per replaced key.
- Single-level: `tanggal_selesai` loses base `after_or_equal:tanggal_mulai`, but both dates are pinned to today by `date_equals` (today ≥ today) — subsumed, accept set identical, as the report claims. `jam_mulai/jam_selesai` nullable→required matches old pass-1+pass-2 conjunction. `alasan` gains `max:500` exactly as old pass 2 required.
- TIDAK_MASUK: overlay rules equal base rules; only the error message for `tanggal_selesai.after_or_equal` changes default → custom (rejection outcome unchanged).
- Null/array `jenis_izin`: `is_string` guard → overlay skipped → base `in:` rule rejects, same as old first-pass rejection. Sound.

**No rule weakened, none strengthened beyond documented message deltas.** IzinPermaValidationTest untouched (diff touches 5 files, no tests).

### authorize(): true — gate-removal audit

Old ordering per method: store ran `authorize('create')` before validate; update/verify methods ran `firstOrFail()` + `authorize(...)` before validate. New ordering: FormRequest validation first, controller gates after. **No gate was removed** — every `$this->authorize()` call survives in the controller body; only order changed. This reorder is inherent to the plan's mandated `authorize(): true` design (plan line 61), and the implementer documented it (delta 1).

## CODE QUALITY — APPROVED (minor findings only)

Correctness verified by rule-equivalence analysis above plus the two focused reads. No dead code left: `Request`/`Rule` imports correctly dropped (no remaining uses in diff-visible code; full-suite-green claim corroborates), `ValidationException` import retained and still used (update() TIDAK_MASUK branch, controller:181). Pattern consistency with Cuti: array-style rules ✓; authorize style ✗ (see finding 3 — but the plan mandates `true`, so code is spec-right).

## Findings

1. **[minor]** `app/Http/Requests/UpdateIzinRequest.php:385-393` — `isNoSuratUpdate()` duplicates `IzinController.php:148-150` logic + re-fetches the Izin. Currently identical predicates (verified), but two sources of truth: if they diverge, `validated()` returns only `no_surat_izin` while the controller walks the full-update path → undefined-key errors / partial data. Fix: make the request method public and have the controller reuse it (or cross-reference comments pinning the pair).

2. **[minor]** `app/Http/Requests/UpdateIzinRequest.php:339,387` — rules() reads Izin state (`verifikasi_atasan`) and runs the `unique('no_surat_izin')` DB probe **before** the controller's `authorize('update')`. An authenticated unauthorized user probing a uuid gets a validation-error oracle distinguishing approved vs non-approved records, plus no_surat enumeration — previously 403 came first. Inherent to the mandated `authorize(): true` design; implementer documented the ordering delta but not this enumeration nuance. Fix: none cheap under the plan; record as accepted delta (or move the gate into `authorize()` as Cuti does).

3. **[minor]** Report inaccuracy (not a code defect): implementer claims the Cuti pattern is "authorize() true with policy-in-controller" — `StoreCutiRequest.php:12` actually gates via `$this->user()->can('create', Cuti::class)` in `authorize()`, preserving pre-validation 403. Code follows the plan's mandate anyway; the "same as Cuti" justification for delta 1 is wrong.

4. **[minor]** `StoreIzinRequest.php:282-312` / `UpdateIzinRequest.php:401-431` — ~60 duplicated lines (`jenisRules`/`jenis`/`messages`). Two copies tolerable; extract a shared trait only when a third consumer appears.

5. **[note]** `StoreIzinRequest.php:276-281` / `UpdateIzinRequest.php:395-400` — docblock conflates the two PERMA citations (Pasal 5 Lampiran II and Pasal 8 Lampiran III) over one function returning either rule set. Split per branch or drop.

6. **[note]** `UpdateIzinRequest.php:392` — `count($this->all()) <= 3` magic-number heuristic (csrf + method + field), pre-existing design now duplicated in two places. Fragile if the form gains a hidden field.

7. **[note]** Nonexistent uuid on update now yields validation errors (full rules fail on missing jenis_izin etc.) instead of 404 — consequence of documented delta 1; the request's `->first()` (null-tolerant) makes this explicit.

8. **[note]** Three behavior deltas in the implementer report (validation-before-authorize, merged single-pass error bag, isNoSuratUpdate re-fetch) all verified real and correctly characterized, except the "same as Cuti" framing in delta 1 (finding 3).

## Verdict

SPEC: **PASS**. QUALITY: **APPROVED** — minors addressable in a follow-up or as-is; no blockers, no rule drift, no removed policy gate.
