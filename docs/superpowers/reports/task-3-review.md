# Task 3 Review: pegawai.user_uuid FK (replace nip join)

**Reviewer verdict: SPEC PASS / QUALITY APPROVED** — with one [major] follow-up required outside the task's file list (F1).
Base a4c02fa → Head f13c5b7. Diff read in full; no cut-off hunks. Outside-diff checks performed (one per named risk):
(i) app-wide `nip` grep — report's grep pattern `where('nip'` was too narrow to catch JOINs; (ii) `Pegawai` refs in
CutiController/IzinController — both imports still used (Cuti:379 `Pegawai::pluck`, Cuti:453 & Izin:77 type params), no dead imports.
Controller-side context taken as confirmed: full migration chain ran clean on real MySQL 8 (incl. the new migration), suite 257 passed on sqlite.

## 1. Spec compliance — Task 3 steps

| # | Step | Verdict | Evidence |
|---|------|---------|----------|
| 1 | Failing test incl. nip-rename-doesn't-break-link | **PASS** | Report RED output (3 failed, QueryException no user_uuid column); `test_renaming_pegawai_nip_keeps_link` present; 5 tests total, both relation directions + null cases. |
| 2 | Migration: nullable foreignUuid after nip, backfill, unique, FK nullOnDelete, complete down() | **PASS** | Column → backfill → unique → FK order correct (unique satisfies FK index need; MySQL reuses it). Correlated-subquery UPDATE instead of plan's `UPDATE...JOIN`: justified deviation — plan's SQL is invalid on SQLite (tests run sqlite :memory:); semantics equivalent (nip unique on pegawai; NULL nip never matches; no-match → NULL no-op under the `WHERE user_uuid IS NULL` guard; ERROR 1093 not triggered — subquery FROMs `users`, not the updated table). `down()` drops FK → unique → column; SQLite FK-drop guard is correct (Blueprint throws; column drop rebuilds table anyway). Nullable unique allows multiple NULLs (pegawai without user) on both MySQL and SQLite. |
| 3 | Swap relations, replace lookups, preserve failure behavior | **PASS** | `Pegawai::user()` → `belongsTo(User, 'user_uuid', 'uuid')`; `User::pegawai()` → `hasOne(Pegawai, 'user_uuid', 'uuid')` — keys correct, old nip belongsTo fully gone, `user_uuid` in $fillable. All `firstOrFail()` sites preserved (`CutiController::store`, `IzinController::store` → `pegawai()->firstOrFail()`); all `first()`-then-null-check sites preserved verbatim (redirect messages unchanged). CutiController:52/61 dereference `->uuid` on a possibly-null relation — identical pre-existing failure mode (old code was `->first()` + property), exactly what the plan asked to preserve. |
| 4 | `grep -rn "where('nip'" app/` — no auth-driven lookups remain, report others | **PASS (literally) / GAP (in spirit)** | Grep run as specified; 1 remaining hit (UserController:86) intentional + reported. But the pattern misses JOINs — see F1. Report's claim "All auth-driven nip lookups gone" is overbroad. |
| 5 | migrate + full suite green | **PASS** | Controller confirmed full chain on MySQL 8; 257 passed (252 baseline + 5). |
| 6 | Commit | **PASS** | Head f13c5b7 delivered per delegating controller. |

### Scope additions — justified vs creep

| Addition | Verdict | Reason |
|---|---|---|
| Policies `pegawaiUuidFor`/`isOwner`/`IzinPolicy::update` nip→uuid | **Justified** | Same comparison class as the swapped lookups; nip-based ownership silently breaks on the very nip-rename the FK now tolerates. Leaving them nip-based would contradict the task's own flagship test. |
| `PegawaiController::detail` → `$pegawai->user` | **Justified (required)** | Manual `User::where('nip')` duplicated the swapped relation; keeping it would desync from the FK and break after nip renames. Null-check + redirect preserved. |
| `UserController::store` link line | **Justified (required)** | Without it, newly provisioned users have null `pegawai` → every swapped lookup fails. nip here is the admin-entered provisioning identifier — the correct remaining use. |
| `SimpegTestCase` user_uuid line | **Justified (required)** | Helper simulated nip-provisioning; suite would be red otherwise. Correct merge order (explicit $pegawaiAttributes still override). |

## 2. Code quality

Migration is clean: ordering correct, idempotent guard, portable SQL, honest comments, complete down(). Model swaps minimal and correct.
Controller/policy edits are uniform one-line swaps preserving null/404 semantics. Unused imports removed only where actually unused (verified). Test file is focused and asserts the right invariant. No over-engineering.

## 3. Findings

1. **[major]** `app/Services/ApproverDirectoryService.php:21,36` — `->join('pegawai', 'users.nip', '=', 'pegawai.nip')` survives in both `pimpinanList()` and `atasanList()`; these feed the Cuti/Izin approver dropdowns. This is the exact User↔Pegawai nip-join Task 3 exists to eliminate (plan Goal names it); after a nip rename the renamed approver silently disappears from both form flows while `Auth::user()->pegawai` keeps working — contradicting the task's headline invariant. Not in the plan's file list and invisible to the plan's prescribed grep pattern, so this is a plan-scope gap rather than implementer error, but it must land. Fix (one line ×2): `->join('pegawai', 'pegawai.user_uuid', '=', 'users.uuid')`. Recommend a follow-up commit before Task 5 closes.
2. **[minor]** `database/migrations/2026_08_23_000000_add_user_uuid_to_pegawai_table.php:19` — `LIMIT 1` without `ORDER BY`: if `users.nip` ever duplicates (no DB unique index — app validation only, report Concern 2), the picked uuid is nondeterministic. Add `ORDER BY u.id` inside the subquery for reproducible backfill. Unique-index creation itself cannot fail from the backfill (nip unique on pegawai ⇒ one uuid per pegawai row).
3. **[note]** `app/Http/Controllers/UserController.php:86` — `Pegawai::where('nip',...)->update(...)`: 0-row update succeeds silently (same as old no-link world), and a pegawai already linked to a different user would be silently re-stolen. Guarded in practice by `'unique:users,nip'` validation at line 66. Acceptable.
4. **[note]** `UserController::update()` nip changes no longer re-link (by design — that is the FK point; report Concern 3). Admin "reassign via nip change" workflow is now manual. Document it; consider an admin re-link tool only if the workflow resurfaces.
5. **[note]** Access-control delta audit: `isOwner` uuid comparison is behavior-equivalent to the nip comparison for all backfilled nip-matched pairs and all post-migration provisioned users; the only divergence is duplicate-user-nip data (pre-existing quality issue, flagged) and the stale-join case in F1. No privilege widening found — null relation → false/null, same as before.

## 4. Verdict

- **SPEC: PASS** — every Task 3 step executed as specified; all scope additions correctness-required.
- **QUALITY: APPROVED** — the diff itself is clean and minimal; F1 is a plan-scope gap requiring a small follow-up, not a defect in the reviewed change set.
