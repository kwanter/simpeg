# Task 4 Implementer Report — routes/web.php cleanup

**Date:** 2026-08-22 · **Branch:** arch-remediation · **Commit:** 4509a5b `refactor(routes): drop duplicate riwayat_jabatan block and no-op outer role group`

## What changed

`routes/web.php` only (1 file, +60/−68):

1. **Deleted unnamed duplicate `riwayat_jabatan` block** (old lines 36–40 + trailing blank): five routes (`GET {pegawai_uuid}`, `GET {pegawai_uuid}/create`, `POST`, `GET {riwayatJabatanId}/edit`, `PUT {riwayatJabatanId}`) that were shadowed by the named `riwayat_jabatan.` prefix group (old lines 51–58). The named group remains the single source of truth (`riwayat_jabatan/{uuid}...` with proper route names).
2. **Deleted the outer no-op role group** (old line 8 wrapper + old line 85 closing brace) and unindented all children. The wrapper applied `role:super-admin|admin|atasan-pimpinan|pimpinan|verifikator|user` to every inner route, but each of the five inner groups already carries its own `role:...` + `auth` + `verified` middleware, and every inner role list is a subset of the outer list — so the outer middleware was authorization-redundant (a user passing any inner role check necessarily passed the outer one).
3. All five inner groups preserved verbatim: roles (super-admin), users (super-admin|admin), hari-libur, pegawai (both super-admin|admin|atasan-pimpinan|pimpinan|verifikator), cuti (incl. user). Dashboard, profile, auth.php, izin blocks untouched.

## route:list diff (verbatim, default view)

```
89,92d88
<   GET|HEAD        riwayat_jabatan/{pegawai_uuid} RiwayatJabatanController@ind…
<   GET|HEAD        riwayat_jabatan/{pegawai_uuid}/create RiwayatJabatanControl…
<   PUT             riwayat_jabatan/{riwayatJabatanId} RiwayatJabatanController…
<   GET|HEAD        riwayat_jabatan/{riwayatJabatanId}/edit RiwayatJabatanContr…
125c121
<                                                           Showing [122] routes
---
>                                                           Showing [118] routes
```

**Why 4 rows, not the predicted 5:** the unnamed block's `POST riwayat_jabatan` had exactly the same method+URI as the named group's `POST riwayat_jabatan` (from `prefix('riwayat_jabatan')` + `Route::post('/', ...)`), so Laravel's RouteCollection had already overwritten it at registration — it never displayed as a separate row. The other four unnamed routes used different parameter names (`{pegawai_uuid}`/`{riwayatJabatanId}` vs `{uuid}`) and/or different path shapes, so they displayed as shadow rows and are the 4 rows removed. Route count 122 → 118.

## Middleware verification (verbose route:list)

Default `route:list` hides the middleware column, so an additional `route:list -v` diff was captured (before state reconstructed via `git stash`). The verbose diff contains exactly two change classes:

1. Line `⇂ Spatie\Permission\Middleware\RoleMiddleware:super-admin|admin|atasan-pimpinan|pimpinan|verifikator|user` removed from every previously-wrapped route (the outer group's middleware) — no other middleware line on any route changed.
2. The 4 duplicate route blocks above removed with their full stacks.
3. Only added line: the `Showing [118] routes` footer. No route gained/lost `auth`, `verified`, permission middleware, or changed action/name.

## Test evidence

```
php artisan test
Tests: 257 passed (502 assertions)
Duration: 10.18s
```

Baseline was 257 passed — no test count change; `RouteMiddlewareTest`, `MiddlewareMatrixTest`, and route:list-based tests (`route cache does not throw`, `no generated route names`) all green.

## Self-review notes

- `php -l routes/web.php` clean after edit.
- Untracked `docs/superpowers/reports/` left uncommitted, consistent with Tasks 1–3 reports.
- Stash used for the verbose before-capture was popped; `git stash list` empty.

## Concerns

- **Minor (informational):** the plan predicted 5 duplicate rows in route:list; only 4 were ever visible (the POST pair collapsed at registration). No behavioral difference — the named `riwayat_jabatan.store` was already the effective route for `POST riwayat_jabatan` before and after this change.
- No other concerns. Effective authorization matrix is provably unchanged (inner role lists ⊆ outer role list; verified in verbose diff).
