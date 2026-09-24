# Final Whole-Branch Review — arch-remediation (SIMPEG)

**Reviewer:** final branch gate (cross-task risks only; per-task gates already PASS/APPROVED)
**Base:** main@4203201 → **Head:** 26ccdd4 (7 commits incl. plan doc 98e6098 and follow-up 8b2dcdf)
**Inputs:** plan `docs/superpowers/plans/2026-08-22-simpeg-arch-remediation.md`, task-{1..5}-review.md, `branch.diff` (file count verified against `git diff 4203201..26ccdd4` — match), direct reads at HEAD, one full test run.
**Mode:** read-only (no mutation, no commits). Verification commands used: git log/diff/show, grep, `php artisan test` (once), `composer audit`.

---

## 0. Test suite honesty — CONFIRMED

`php artisan test` run by this reviewer: **257 passed, 502 assertions, 10.56s** — matches the claimed baseline exactly (245 pre-branch + 7 IzinApprovalRace + 5 UserPegawaiRelation). No skipped/errored tests hidden in the tail.

---

## 1. Composition (cross-task consistency) — CLEAN

- **Service ↔ FormRequests ↔ Controller:** `prosesVerifikasiAtasan(VerifyAtasanIzinRequest $request, $uuid)` / `prosesVerifikasiPimpinan(VerifyPimpinanIzinRequest $request, $uuid)` type-hints match route params (`{uuid}`); validated keys (`verifikasi_atasan`/`catatan_atasan`, `verifikasi_pimpinan`/`catatan_pimpinan`) match service params exactly. Store/Update signatures match `Route::resource('izin', …)` methods.
- **State machine coherence:** controller `authorize('verifyAtasan')` gates on `verifikasi_atasan == 'Belum Diverifikasi'`; service locks on `status == 'Diajukan'`. Store creates both consistently (status `Diajukan`, both flags `Belum Diverifikasi`); nothing writes either field except the service and the (field-restricted) update paths, so the two guards never diverge. `applyPimpinan` requires `status == 'Disetujui Atasan'`; single-level jenis never reach it (atasan approve sets `Disetujui` terminal) AND `IzinPolicy::verifyPimpinan` returns false for single-level — double-guarded.
- **Faithful port check:** pre-branch inline code (`git show 4203201`, prosesVerifikasi*) produces byte-identical state transitions, including the single-level atasan-reject → `Ditolak Atasan` corner. `now()`→`Carbon::now()` equivalent. Only behavior changes are the two intended ones: race guard + new pimpinan precondition.
- **FK relation ↔ consumers:** `User::pegawai()` hasOne(`user_uuid`→`uuid`) / `Pegawai::user()` belongsTo(`user_uuid`,`uuid`) keys correct; all consumers (CutiController 46-61/96-97/254/274, IzinController 44/94/247/266, CutiPolicy, IzinPolicy, IzinQueryService, PegawaiController::detail `$pegawai->user`, ApproverDirectoryService joins) uniformly use the relation. Soft-delete semantics equivalent on both sides (both models SoftDeletes; old nip lookup also excluded soft-deleted).
- **Routes ↔ middleware:** FormRequests resolve after route middleware (`auth`+`verified` on the izin group, web CSRF) — guests never reach validation; unassigned-but-authenticated users hit `authorize()` 403 after trivially-passing validation; no write path bypasses policy.
- **Unreviewed-commit coverage gap:** 8b2dcdf (approver join fix) fell between task-3's head (f13c5b7) and task-4's base — no task gate reviewed it. This review did: both `ApproverDirectoryService` joins correctly swapped to `pegawai.user_uuid = users.uuid`; backfill now `ORDER BY u.uuid LIMIT 1` (deterministic; resolves task-3 F1+F2). Verified correct.

## 2. Security-audit guarantees (SEC-001..018) — NO REGRESSION

| Guarantee | Check at HEAD | Verdict |
|---|---|---|
| SEC-001/IDOR owner checks after uuid switch | CutiPolicy + IzinPolicy diffs are pure `Pegawai::where('nip')…` → `$user->pegawai?->uuid` swaps; null-relation → false/null, same as old no-match; no privilege widening found | INTACT |
| SEC-002 private docs | `IzinController::downloadDocument` still `authorize('view')` + storage-exists check; untouched | INTACT |
| SEC-003/006/009 route hygiene & verified | `route:cache` + no-generated-names tests green; izin group carries `auth+verified`; guest izin test green | INTACT |
| SEC-011 admin-vs-super-admin isolation | UserPrivilegeTest green; UserController untouched by risk paths | INTACT |
| SEC-012 (cuti race) | CutiApprovalService untouched | INTACT |
| SEC-012-equivalent (izin) | IzinApprovalService: DB::transaction + `lockForUpdate()` re-fetch by uuid + expected-status guard; TOCTOU between policy read and service lock closes at the lock | STRENGTHENED |
| SEC-017 izin update PERMA rules | Rules moved verbatim into UpdateIzinRequest (task-2 key-by-key check accepted; IzinPermaValidationTest green) | INTACT |
| SEC-018 cuti index scoping | CutiController::index role/owner/assignment scope intact, lookups now via relation | INTACT |
| SEC-018-equivalent izin index | IzinQueryService::forUser scoping unchanged (3-line diff = relation swap); unlinked user → `whereRaw('1=0')` | INTACT |
| Route middleware matrix after wrapper removal | Outer no-op role group removal proven auth-neutral (inner role lists ⊆ outer superset; hasAnyRole semantics); MiddlewareMatrixTest + RouteMiddlewareTest green at HEAD | INTACT |
| SEC-015/010/016 | SafeEmail, upload MIME, document-lifecycle code untouched by branch | INTACT |

One real (accepted) delta: validation-before-authorize on izin endpoints — see Finding 1.

## 3. Deferred / Laravel 12 upgrade risks — NOT WORSENED

- Removing sanctum/breeze/intervention/predis and the `illuminate/database: "*"` wildcard *shrinks* the L12 upgrade surface; no dangling refs (repo grep: only `phpredis` config string, benign).
- `personal_access_tokens` migration retained — inert without package; harmless if sanctum ever returns.
- `composer audit` re-run by reviewer: advisories remain on **kept** deps (e.g. league/commonmark CVE-2026-71478, laravel/framework) — exactly the deferred RISK-001 set; unchanged in kind, one fewer package in count.
- foreignUuid/unique/nullOnDelete migration, FormRequests, HasUuids — all forward-compatible with L12 idioms.

## 4. Dead code / leftovers from the arc — MINIMAL

- Deleted private validators (`validateIzinKeluarKantor`/`validateIzinTidakMasukKerja`): zero references anywhere (app/tests/resources).
- IzinController import list fully live (IzinType, ValidationException, Str, Auth, Pegawai all used).
- Remaining debt: Finding 2 (unused service params) and Finding 3 (duplicated predicate). Nothing else.

---

## Findings

1. **[minor]** `app/Http/Requests/UpdateIzinRequest.php:17-39,66-74` (+ Store/Verify* requests) — FormRequest validation now runs before controller `$this->authorize()`: an authenticated but unauthorized user probing a uuid gets a 422 oracle (record existence, approval state via the `isNoSuratUpdate` branch, `no_surat_izin` uniqueness) where pre-branch they got 403/404 first. No write-path bypass (authorize still precedes every mutation); izin-only; cuti requests still gate pre-validation. Fix: gate in `FormRequest::authorize()` via `$this->user()->can('update', $izin)` like `StoreCutiRequest` does.
2. **[minor]** `app/Services/IzinApprovalService.php:18,39` + `app/Http/Controllers/IzinController.php:247,266` — `?Pegawai $atasan`/`?Pegawai $pimpinan` accepted, resolved, passed — and never read (izin has no who-approved column). Dead interface + one wasted relation load per approval. Fix: drop both params and the two resolutions.
3. **[minor]** `app/Http/Controllers/IzinController.php:148-150` vs `app/Http/Requests/UpdateIzinRequest.php:66-74` — `isNoSuratUpdate` predicate (incl. `count($request->all()) <= 3` heuristic) duplicated in two places; divergence would corrupt the no-surat update path. Fix: expose the request's predicate as public and reuse it in the controller.
4. **[note]** `resources/views/welcome.blade.php:111` — "Sanctum" marketing hyperlink in default welcome view; cosmetic, no code reference.
5. **[note]** `docs/superpowers/reports/` untracked — the five task reviews, diffs and this report are not in git while the plan is (98e6098). Decide: commit or gitignore, so the audit trail isn't accidentally lost on clean.
6. **[note]** `app/Exceptions/Handler.php:31` — Log facade inside reportable closure can mask boot-time errors; pre-existing, already filed via task-5. Fix belongs in a follow-up, not this branch.
7. **[note]** Process: commit 8b2dcdf landed between task gates and was never task-reviewed (covered here instead). For future arcs, give remediation commits their own review file.

## Verdict

**FINDINGS — none blocking.** No blockers, no majors. All five tasks compose cleanly at HEAD; SEC-001..018 guarantees hold (izin race-safety strictly improved); the branch reduces rather than increases Laravel 12 upgrade risk; the 257-test baseline claim is honest (re-run independently). The three minors are small, isolated cleanups that can land as a follow-up commit before merge or immediately after — none justifies re-opening the branch.

| Dimension | Verdict |
|---|---|
| Composition (service/requests/FK/routes) | CLEAN |
| SEC-001..018 regression | NONE (one accepted ordering delta → Finding 1) |
| Deferred-risk (L12) impact | IMPROVED |
| Test honesty (257) | CONFIRMED by independent run |
| Dead code | MINIMAL (Findings 2-3) |
| **Branch** | **APPROVE FOR MERGE** |
