# Specification: Laravel 12 Framework Upgrade

> Version 1.0 · Created 2026-11-07 · Status Draft for approval
> Ref: `plan/plan-refactor-pkp-dependencies-v1.0.md` TASK-40Y

## 1. Purpose & Scope

Move SIMPEG from `laravel/framework ^10.10` (locked 10.50.2, out of security
support since 2025-02-04) to `^12.69`, which closes the four open framework
advisories. In scope: `composer.json` constraints, the lockfile, the
version-floor guard, the Carbon 3 tenure behaviour, and PHPUnit 11 annotation
migration. Out of scope: Laravel 13, application structure migration, Docker
image PHP versions, and any schema change.

### Non-goals

- No `php artisan migrate` behaviour change; no new migration.
- No restructuring to the Laravel 11 skeleton. Upstream states the 10 structure
  remains supported.
- No change to `docker/apache/Dockerfile` (PHP 8.2) or the CI matrix, because
  Laravel 12 keeps a PHP 8.2 floor.

## 2. Definitions

- **Floor:** the minimum locked version that clears an advisory.
- **Seam:** a public boundary where a behaviour is pinned by an automated test.
- **Signed diff:** Carbon 3's `diffIn*` return value, which is negated when the
  receiver is later than the argument. Carbon 2 returned an absolute value.
- **Guard:** `tests/Unit/DependencyFloorTest.php`, which fails when a locked
  version drops below a floor.

## 3. Requirements / Constraints / Guidelines

- **REQ-001:** `laravel/framework` resolves at or above 12.69.0, the highest
  advisory floor among the four open advisories.
- **REQ-002:** `composer audit` reports zero advisories after the upgrade. The
  four-GHSA baseline in `.github/ComposerAuditPolicy.php` becomes obsolete, not
  extended.
- **REQ-003:** The Carbon 3 tenure calculation keeps its Laravel 10 result. A
  pegawai with at least five years of service may request Cuti Besar; one with
  fewer may not.
- **REQ-004:** The suite runs on `phpunit/phpunit ^11` with no deprecated
  annotation metadata: `@dataProvider` becomes the `#[DataProvider]` attribute.
- **REQ-005:** Dev tooling moves together: `nunomaduro/collision ^8.9` and
  `spatie/laravel-ignition ^2.12`, because collision 7 forbids the framework
  line and ignition 2.13 demands PHP 8.4.
- **REQ-006:** `brick/math` stays at `^0.12.3`. Laravel 12 accepts it, so exact
  PKP arithmetic is untouched.
- **REQ-007:** `spatie/laravel-permission` stays on its `^6.9` line, resolving
  to 6.25.0. No `hasRole`/`hasAnyRole`/`hasPermissionTo`/`syncRoles` call site
  changes.
- **CON-001:** PHP floor stays 8.2. CI keeps the 8.2, 8.3, and 8.4 matrix.
- **CON-002:** No suppression, no skipped test, no relaxed assertion, no
  lowered floor. If the upgrade breaks a behaviour, fix the behaviour or hold
  the release.
- **GUD-001:** Prefer the smallest diff that satisfies REQ-001 through REQ-007.
  Do not adopt Laravel 11+ conventions opportunistically.

## 4. Interfaces & Data Contracts

### 4.1 Composer constraints

| Package                     | From     | To       |
| --------------------------- | -------- | -------- |
| laravel/framework           | ^10.10   | ^12.69   |
| nunomaduro/collision        | ^7.0     | ^8.9     |
| phpunit/phpunit             | ^10.1    | ^11.0    |
| spatie/laravel-ignition     | ^2.0     | ^2.12    |
| spatie/laravel-permission   | ^6.9     | ^6.9     |
| brick/math                  | ^0.12.3  | ^0.12.3  |
| laravel/tinker              | ^2.8     | ^2.8     |

`spatie/laravel-backup` is already absent and stays absent.

### 4.2 Tenure contract

`CutiEligibilityService::checkCutiBesar(Pegawai, Carbon, Carbon, ?string)`
returns `null` when the pegawai has at least five years of service and has no
approved Cuti Tahunan in the requested year, and an Indonesian message
otherwise. The service must express the elapsed years as a non-negative
quantity regardless of Carbon major.

### 4.3 Test metadata contract

Every data-provider consumer in `tests/` carries `#[DataProvider('method')]`
and `use PHPUnit\Framework\Attributes\DataProvider;`. Zero `@dataProvider`
doc-comments remain.

### 4.4 Audit contract

After the upgrade, `composer audit --locked` exits 0 with no advisories.
`.github/ComposerAuditPolicy.php` may keep its four-entry baseline for
historical reference, but the gate must pass because the report is empty, not
because a baseline accepts it.

## 5. Acceptance Criteria & Testable Behaviors

- **AC-001:** `tests/Unit/DependencyFloorTest.php` asserts
  `laravel/framework >= 12.69.0` and fails before the upgrade.
- **AC-002:** A characterization test pins Cuti Besar eligibility for a pegawai
  with six years of service to `null` (accepted), with `Carbon::setTestNow`
  fixing the clock.
- **AC-003:** The same test pins rejection for a pegawai with four years of
  service, and the message names the five-year rule.
- **AC-004:** No file under `tests/` contains `@dataProvider`.
- **AC-005:** The full PHP suite passes with zero failures and zero skipped
  tests.
- **AC-006:** `composer audit` exits 0 with no advisories.
- **AC-007:** `npm audit --include=dev --audit-level=high` still exits 0.
- **AC-008:** Both Chromium Playwright suites pass: the base suite and the PKP
  suite.
- **AC-009:** `npm run build` succeeds and the manifest references the built
  assets.
- **AC-010:** The PKP PDF path still produces a PDF with a `%PDF-` header that
  passes `qpdf --check`, proving `barryvdh/laravel-dompdf` and `brick/math`
  survive the framework bump.

## 6. Test Automation Strategy & Seams

- **Seam 1 — lockfile floor:** `DependencyFloorTest`. Reads `composer.lock`
  directly; no framework boot. Fails the moment a floor regresses.
- **Seam 2 — eligibility service:** `CutiEligibilityServiceTest`. Exercises the
  tenure math through the public method with a fixed clock, so a Carbon
  semantic change cannot pass unnoticed.
- **Seam 3 — PKP score rendering:** existing `PkpPdfTest`, which asserts real
  PDF bytes and exact rational output. This is the `brick/math` regression net.
- **Seam 4 — route and policy behaviour:** existing feature tests. A framework
  bump that changes middleware or route resolution order surfaces here.

The upgrade itself is not test-first in the red-green sense; the RED step is
AC-001, and the remaining work is characterization under the existing suite.
Nothing in the upgrade may delete or weaken an existing test.

## 7. Project Structure & Executable Commands

```bash
composer update laravel/framework nunomaduro/collision phpunit/phpunit \
  spatie/laravel-ignition spatie/laravel-permission --with-all-dependencies

vendor/bin/pint --test
PKP_QPDF_BINARY=/opt/homebrew/bin/qpdf php artisan test
composer audit --locked
npm audit --include=dev --audit-level=high
npm run build
npx playwright test --project=chromium
npx playwright test --config=playwright.pkp.config.js --project=chromium
```

`PKP_QPDF_BINARY` is required for the PKP document tests; without it they
return 503 `pdf_validator_unavailable`.

## 8. Implementation Guardrails

- `composer update` must not use `--ignore-platform-reqs` or force a package
  outside a parent's declared range.
- If a transitive package blocks resolution, stop and report the specific
  conflict; do not add an `overrides`-style workaround.
- If an application test fails, fix the application seam the upgrade exposed —
  never the assertion.
- When the framework version changes, clear the compiled route cache before
  re-running routing tests.

## 9. Rationale & Decisions

- **Why 12 over 13:** Laravel 12 closes all four advisories and keeps the PHP
  8.2 floor, so no runtime image or CI matrix moves. Laravel 13 additionally
  needs `laravel/tinker ^3.0` and `brick/math ^0.14.2 || ^1.0` and drops PHP
  8.2. Recorded as `docs/adr/0002-laravel-12-upgrade-target.md`.
- **Why not Laravel 11:** the CRLF advisory covers `illuminate/mail` 11.x and
  Composer refuses to install that line under its advisory policy.
- **Why Carbon needs a change:** Carbon 3 makes `diffInYears` signed. The
  receiver `Carbon::now()` is later than the hire date, so the raw result turns
  negative and the five-year check rejects every employee. The fix keeps the
  same contract by asking for a non-negative value.
- **Why the baseline policy stays:** deleting it removes the guard that catches
  a *new* advisory. With an empty report it is inert; with a future finding it
  fails closed.
