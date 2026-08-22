# Task 4 Review — routes/web.php cleanup

**Date:** 2026-08-22 · **Reviewer:** task-scoped gate (spec + quality)
**Base:** 8b2dcdf · **Head:** 4509a5b · **Diff:** docs/superpowers/reports/task-4.diff

## SPEC COMPLIANCE — PASS

| Plan step | Verdict | Evidence |
|---|---|---|
| `route:list > /tmp/routes-before.txt` | Done (per report) | report §route:list diff |
| Delete lines 36–40 (unnamed `riwayat_jabatan` block) | PASS | diff: exactly 5 bare `Route::get/post/put('riwayat_jabatan…')` lines + 1 blank deleted, zero `->name()` on any deleted line |
| Delete outer no-op role wrapper (line 8 + close line 85), unindent children | PASS | diff: `role:super-admin\|admin\|atasan-pimpinan\|pimpinan\|verifikator\|user` wrapper open deleted; net line delta −8 = 5 routes + 1 blank + wrapper open + wrapper close; current file brace balance 43/43, parens 143/143; cuti group closes cleanly, dashboard/profile/`require auth.php`/izin blocks intact after it (no merged groups, no dropped brace) |
| `route:list` diff — "expect only the 5 duplicate rows gone" | PASS w/ note | 4 rows gone (122→118), not 5 — see verification below; nothing else changed (footer line only) |
| `php artisan test` green | Claimed PASS | report: 257 passed / 502 assertions, count unchanged vs baseline; not re-run by reviewer (read-only mandate) — report evidence stands unchallenged |
| Commit `refactor(routes): drop duplicate riwayat_jabatan block and no-op outer role group` | PASS | report; exact conventional-commit form required by Global Constraints |

### Independent verification of the four named risks

1. **Only outer wrapper + duplicate block removed** — Confirmed. Every non-deleted line in the diff is a pure re-indent (4-space shift); middleware arrays, route URIs, actions, name() calls byte-identical old→new. Hunk math: 82→74 lines, net −8, fully accounted for.
2. **Inner groups' middleware intact** — Confirmed. Five inner groups verbatim: roles `role:super-admin`+auth+verified; users `role:super-admin|admin`+auth+verified; hari-libur and pegawai `role:super-admin|admin|atasan-pimpinan|pimpinan|verifikator`+auth+verified; cuti `role:…|user`+auth+verified. Outer-removal is provably auth-neutral: Spatie `role:a|b` = hasAnyRole, and every inner role list ⊆ outer list, so inner ANY(subset) ∧ outer ANY(superset) ≡ inner ANY(subset).
3. **No route name lost** — Confirmed. All five deleted routes were bare (no `->name()` in the deleted diff lines); kept named block (`riwayat_jabatan.` prefix, 6 named routes incl. `destroy` the duplicate lacked) untouched. Current-file name() inventory matches pre-change set minus nothing.
4. **4-not-5 explanation consistent with diff** — Confirmed. Deleted `Route::post('riwayat_jabatan', …)` and kept `Route::prefix('riwayat_jabatan')` + `Route::post('/', …)` produce the same method+URI; Laravel `RouteCollection::addToCollections` keys on method+domain+URI and overwrites, so the later-registered named POST had already replaced the unnamed one at registration — never a visible route:list row. The 4 removed rows are exactly the routes with differing param names/shapes (`{pegawai_uuid}`, `{riwayatJabatanId}`). Count 122→118 (−4) checks out. Plan's "5 rows" prediction was mildly wrong, not the implementation.

## CODE QUALITY — APPROVED

Pure deletion + re-indent; zero logic added or reordered. Constraint compliance: no framework upgrade, no new deps, no status-string changes, tests claimed green before commit, commit message matches required form.

## Findings

1. **[note]** routes/web.php:36 (old) — plan predicted 5 route:list rows; only 4 ever visible (POST pair collapsed at registration). Implementer documented root cause correctly. Fix: none needed; plan doc could be corrected for posterity.
2. **[note]** report §What changed — "the named `riwayat_jabatan.` prefix group" routes are actually named `pegawai.riwayat_jabatan.*` (group is nested inside `->name('pegawai.')`). Cosmetic imprecision in the report, not the code.
3. **[note]** routes/web.php:38 — the blank line between `Route::resource('jabatan', …)` and the `riwayat_pangkat` prefix group was removed with the dead block. Cosmetic.

No blockers. No majors. No minors.

## Verdict

- SPEC: **PASS**
- QUALITY: **APPROVED**
