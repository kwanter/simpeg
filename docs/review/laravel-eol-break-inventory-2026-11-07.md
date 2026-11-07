# Laravel End-of-Life Break Inventory (2026-11-07)

Fixed point: `5cb5653b8ad19a412e638faa36c0b7f8a8254e3f`. This document is
analysis only. No dependency, lockfile, workflow, or application file was
changed while producing it.

## 1. Scope

`plan/plan-refactor-pkp-dependencies-v1.0.md` records that four Laravel
advisories cannot be closed inside `^10.10`. This inventory answers the two
questions that decision needs:

- Which dependency releases make a supported framework version resolvable.
- What in this application actually breaks between Laravel 10 and that
  version.

Evidence comes from `composer update --dry-run --no-install` resolution in
throwaway directories with `config.platform.php` pinned, from the official
upgrade guides, and from source scans of this repository.

## 2. What closing the advisories requires

| Advisory                   | 12.x floor |
| -------------------------- | ---------- |
| GHSA-f9x3-7vq9-cph6        | 12.68.0    |
| GHSA-jh5r-qr3c-85q8        | 12.69.0    |
| GHSA-crmm-hgp2-wgrp        | 12.61.1    |
| GHSA-5vg9-5847-vvmq        | 12.60.0    |

The highest floor is **12.69.0**, so a Laravel 12 resolution must stay at or
above it.

Laravel 11 is **not** a viable intermediate stop. The same CRLF advisory
covers `illuminate/mail` `>=11.0.0,<12.0.0`, and Composer 2.10's advisory
policy refuses to install that line at all: a probe asking for
`spatie/laravel-backup ^8.0` failed with "these were not loaded, because they
are affected by security advisories (PKSA-zwc5-qtrz-zm1n)". Any path through
Laravel 11 is both unsupported and un-installable.

## 3. Dependency resolution results

Each probe used a throwaway directory holding only a modified copy of
`composer.json`, then ran
`composer update --dry-run --no-install --no-scripts --no-plugins`.

- Unchanged manifest, `laravel/framework ^12.0`, PHP 8.2: blocked.
  `spatie/laravel-backup 8.8.2` requires `illuminate/* ^10.10.0|^11.0`, and
  `nunomaduro/collision ^7.0` conflicts with `laravel/framework >=11.0.0`.
- `spatie/laravel-backup ^10.4`, `spatie/laravel-permission ^8.5`,
  `laravel/framework ^12.69`, PHP 8.2: blocked on PHP.
  Backup 10.4 requires PHP `^8.4`, permission 8.5 requires PHP `^8.3`, and
  collision 8.9 conflicts with `phpunit/phpunit 10.5.6x`.
- `spatie/laravel-backup ^9.4`, `spatie/laravel-permission ^6.25`,
  `spatie/laravel-ignition ^2.12`, `nunomaduro/collision ^8.9`,
  `phpunit/phpunit ^11.0`, PHP 8.3: resolvable.
- The same set at PHP 8.2: blocked, because backup 9.4 requires PHP `^8.3`.
- The same set with `spatie/laravel-backup` **removed**, PHP 8.2:
  **resolvable**.

The resolvable Laravel 12 set on PHP 8.2 locks:

- `laravel/framework` v12.71.0
- `brick/math` 0.12.3 (unchanged)
- `spatie/laravel-permission` 6.25.0 (within the existing `^6.9` line)
- `spatie/laravel-ignition` 2.12.1, `nunomaduro/collision` v8.9.5,
  `phpunit/phpunit` 11.5.57
- `symfony/*` 7.4.x, `nesbot/carbon` 3.15.1, `laravel/prompts` 0.3.26
- `venturecraft/revisionable` 1.43.0, `barryvdh/laravel-dompdf` v3.1.2,
  `sentry/sentry-laravel` 4.29.0, `laravel/ui` v4.6.3

## 4. Finding: the backup package is unreferenced in code

`spatie/laravel-backup` is required in `composer.json` but no application code
uses it:

- `config/backup.php` does not exist.
- No reference exists in `app/`, `routes/`, `resources/`, `bootstrap/`, or the
  environment files.
- `app/Console/Kernel.php` schedules only `cuti:update-balances`.
- The only code-side hit is package auto-discovery metadata in
  `bootstrap/cache/packages.php`.

Documentation is a different story, and it contradicts itself:

- `PRODUCTION-RUNBOOK.md:97` makes the package a release gate: "Configure
  `spatie/laravel-backup` destination outside app host."
- `VPS-DEPLOYMENT-GUIDE.md:636-671` implements backup without it, through a
  custom `pg_dump` plus `storage` tarball script driven by systemd.

Both cannot be true of the same deployment. The runbook gate names a package
that ships no configuration and runs no schedule here, while the deployment
guide describes the mechanism that actually exists. Dropping the package
therefore keeps the PHP floor at 8.2 — the PHP 8.3 requirement in probes three
and four came only from `spatie/laravel-backup` — but it also obliges revising
`PRODUCTION-RUNBOOK.md:97` in the same change. Leaving that line untouched
would leave a release gate pointing at nothing.

Keeping the package is the alternative, and it costs more than it looks:
backup 9.4 raises the PHP floor to 8.3, and the package still has no
`config/backup.php` and no schedule entry, so it would not take a backup until
both were added — duplicating what the systemd script already does.

## 5. Application break surface, Laravel 10 to 12

- **Carbon 3:** one call site, `app/Services/CutiEligibilityService.php:48`
  (`Carbon::now()->diffInYears(...)`). Carbon 3 changed `diffIn*` semantics,
  so this line needs a test before and after.
- **PHPUnit 11:** 19 `@dataProvider` doc-comment annotations across `tests/`.
  They still work but are deprecated; convert to `#[DataProvider]`
  attributes.
- **Local disk root (12.x):** inert. `config/filesystems.php:33` already
  declares `storage_path('app')` explicitly, so the new private default does
  not apply to the eight `Storage::disk('local')` call sites.
- **Route precedence (12.x):** inert. `routes/` declares no duplicate route
  names.
- **Modifying columns, floating-point types, `unsigned*` helpers:** zero
  `->change()` calls, zero `float`/`double` columns with arguments, zero
  `unsignedDecimal`/`unsignedFloat`/`unsignedDouble` in
  `database/migrations/`. `doctrine/dbal` is not installed.
- **Other 12.x changes:** zero `mergeIfMissing`, zero `perSecond` limiters,
  zero `needsRehash`, zero `once()` calls.
- **Spatie permission API:** 20 `hasRole`, 27 `hasAnyRole`, 5
  `hasPermissionTo`, 2 `syncRoles` call sites, all stable in 6.25.
- **Application structure:** the 11.x guide states Laravel 11 was tuned to
  support the Laravel 10 structure and advises against restructuring. The
  custom `app/Http/Kernel.php`, `app/Console/Kernel.php`, and
  `app/Exceptions/Handler.php` stay as they are.
- **SQLite:** local version 3.54.1, above the 3.26 floor the 11.x guide
  requires for the test database.

## 6. brick/math

Laravel 12 accepts `brick/math ^0.11|^0.12|^0.13|^0.14`, so the current
`^0.12.3` pin survives a Laravel 12 upgrade untouched.

Laravel 13 requires `^0.14.2` or newer. The `1.0.0` release is byte-for-byte
0.20.0 per its changelog, and every method this repository uses
(`BigDecimal::of`, `plus`, `multipliedBy`, `dividedBy`, `compareTo`, `isZero`,
`BigRational::nd`) is unchanged across that range.

brick/math 0.20 also hardened `of()` against malformed input that triggered
heavy parser backtracking. That hardening is **not** reachable through PKP
today: decimal fields are gated by a strict regex and then by string-length
and string-comparison range checks
(`app/Http/Requests/SavePkpRequest.php:84-90`) before any value reaches
`BigDecimal`/`BigRational`.

## 7. Options

### Decision required: drop or keep the backup package

This is a product decision, not a resolver outcome, because the two documents
disagree about it. Both branches are recorded here; neither is assumed.

- **Drop:** the PHP floor stays 8.2, and `PRODUCTION-RUNBOOK.md:97` must be
  revised in the same change to point at the systemd script in
  `VPS-DEPLOYMENT-GUIDE.md` instead of the package. The release gate survives;
  only its mechanism changes.
- **Keep:** bump to `spatie/laravel-backup ^9.4`, accept PHP 8.3, and add the
  `config/backup.php` and schedule entry the package needs before it can take
  a backup — work the systemd script already does.

### Option A: Laravel 12.71 on PHP 8.2 (recommended)

- Drop `spatie/laravel-backup` and revise `PRODUCTION-RUNBOOK.md:97`.
- Raise `spatie/laravel-permission` to `^6.25` (same major).
- Dev tooling: `nunomaduro/collision ^8.9`, `phpunit/phpunit ^11.0`,
  `spatie/laravel-ignition ^2.12`.
- No Docker, CI-matrix, or runtime PHP change: the framework floor stays 8.2,
  matching `docker/apache/Dockerfile` and the `8.2` CI entry.
- Closes all four advisories because the resolution lands on 12.71.0.

### Option A′: Laravel 12.71 on PHP 8.3, keeping the package

- Everything in Option A except the drop: `spatie/laravel-backup ^9.4`, plus
  `config/backup.php` and a schedule entry, plus `docker/apache/Dockerfile`
  and the CI matrix moving to 8.3.
- Keep this only if the release gate must stay a framework feature rather than
  the systemd script.

### Option B: Laravel 13.40 on PHP 8.3

- Everything in Option A, plus `laravel/tinker ^3.0` (2.11.1 caps at
  Laravel 12) and `brick/math ^0.14.2 || ^1.0`.
- Requires PHP 8.3: `docker/apache/Dockerfile` and the CI matrix must drop
  8.2. `spatie/laravel-backup ^10.4` and `spatie/laravel-ignition 2.13` would
  push it to 8.4 if either is upgraded later.
- One major beyond what the advisories require, for no additional advisory
  coverage.

## 8. Recommendation

Take Option A, and settle the backup question with it rather than separately:
the deployment guide already documents a working `pg_dump` plus storage
tarball script, so the `spatie/laravel-backup` release gate in
`PRODUCTION-RUNBOOK.md:97` is the stale half of that contradiction. Dropping
the package closes every open framework advisory with the smallest change
set, keeps the PHP 8.2 floor, and touches no runtime image.

If you would rather the release gate stay a framework feature, take Option A′
instead and accept PHP 8.3. Do not ship Option A with an unrevised runbook
gate.

The two behaviours that need explicit tests before and after are the Carbon 3
tenure calculation in `CutiEligibilityService` and the PKP PDF/score output
that depends on `brick/math`.

## 9. What this document does not authorize

No dependency was changed. The upgrade itself needs its own specification and
plan, and `TASK-40Y` in `plan/plan-refactor-pkp-dependencies-v1.0.md` stays
open until that is approved.
