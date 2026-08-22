# Task 2 Implementer Report — Izin FormRequests

## What I did

Extracted ALL inline validation from IzinController into four FormRequest classes, following the existing Cuti request pattern (StoreCutiRequest/UpdateCutiRequest: array-style rules, authorize() true with policy-in-controller comment).

- `StoreIzinRequest` — base store rules + conditional PERMA jenis rules + conditional custom messages.
- `UpdateIzinRequest` — no_surat-only branch OR full update rules (base minus pegawai_uuid) + conditional PERMA jenis rules/messages.
- `VerifyAtasanIzinRequest`, `VerifyPimpinanIzinRequest` — verbatim verify rules.
- `IzinController` — signatures swapped to typed requests, `$request->validated()` used, both private `validateIzin*` methods deleted, both inline `$request->validate` blocks in store/update and the two verify methods removed. Unused imports (Request, Rule) dropped. Net: -92/+15 lines.

## Design decision: jenis-specific rules via conditional merge

Original code validated in two passes: base `$request->validate()` first, then a jenis-specific second pass (single-level → Izin Keluar Kantor rules; TIDAK_MASUK → Tidak Masuk Kerja rules) whose return value was discarded. Moving to FormRequest merges the jenis rules into one pass via `array_merge` keyed on the submitted `jenis_izin` (null-safe: `is_string` guard before `IzinType::isSingleLevel()`, which has a strict string param — original code never passed null because the first validate() threw first).

Accept-set equivalence verified:
- Single-level jenis: original required both dates = today (pass 2) which implies base after_or_equal (pass 1). Merged rules require both dates = today. Identical accept set; base cross-date after_or_equal is redundant under date_equals-on-both and is subsumed.
- TIDAK_MASUK: identical rules (base after_or_equal == jenis after_or_equal), only the message becomes the custom one (was default in pass 1; same rule either way).
- Other jenis: base rules only, defaults messages — unchanged.

Custom messages are returned by `messages()` ONLY when the matching jenis branch is active, preserving original pass-1-default/pass-2-custom behavior for other jenis.

## Files changed

- Created: app/Http/Requests/StoreIzinRequest.php, UpdateIzinRequest.php, VerifyAtasanIzinRequest.php, VerifyPimpinanIzinRequest.php
- Modified: app/Http/Controllers/IzinController.php
- Untouched: tests/Feature/IzinPermaValidationTest.php

## Test evidence

- `php artisan test --filter IzinPermaValidationTest` → 9 passed (21 assertions).
- `php artisan test` (full) → **252 passed (497 assertions)** — baseline held.
- `php -l` clean on controller + all four requests.

## Rule-drift check (before/after comparison method)

Manual key-by-key comparison of every rule array from `git show HEAD:app/Http/Controllers/IzinController.php` against the new request classes: store base (10 keys), update full (9 keys, = store minus pegawai_uuid), keluar-kantor overlay (5 keys + 3 messages), tidak-masuk overlay (3 keys + 1 message), no_surat branch (unique ignore uuid), verify atasan/pimpinan (2 keys each). Constraints identical; only notation changed pipe-string → array (same semantics, matches Cuti style).

## Known deltas (inherent to FormRequest pattern, not rule drift)

1. Validation now runs before controller `$this->authorize()`/404 model lookup (task mandates authorize(): true; same as existing Cuti requests). Invalid input + unauthorized/no-model now yields a validation redirect instead of 403/404 in edge cases.
2. Single validation pass → error bag can contain base+jenis messages together where the old two-pass showed only pass-1 errors first. Accept/reject outcome identical; regression test green.
3. `UpdateIzinRequest::isNoSuratUpdate()` re-fetches Izin by route uuid (controller still computes its own flag for flow control) — one extra query on the no_surat path only.

## Concerns

None blocking. The three deltas above are the standard cost of the FormRequest pattern already used by Cuti in this repo.
