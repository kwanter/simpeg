# Monthly PKP Verification Record

**Spec:** `spec/spec-feature-monthly-pkp.md` v0.4  
**Plan:** `plan/plan-monthly-pkp.md` v1.2  
**Verification date:** 2026-10-07

## Functional and Regression Gates

| **Gate** | **Result** |
| --- | --- |
| Full PHPUnit | Pass: 595 tests, 4,557 assertions (87.61 seconds). |
| PHP formatting | Pass: all 274 PHP files. |
| Frontend build | Pass. |
| Base Chromium | Pass: 2 guest-flow tests. |
| PKP Chromium | Pass: 12 real-session PKP and normal-motion QA scenarios. |
| PostgreSQL concurrency | Pass: 12 tests, 104 assertions on PostgreSQL 16. |
| MySQL concurrency | Pass: 12 tests, 104 assertions on MySQL 8.0. |
| Upload envelope | Pass: 1 test, 33 assertions through real Nginx/PHP-FPM. |
| qpdf runtime parity | Pass: v12.2.0 in PHP-FPM, Apache, workspace. |
| Documentation format | Pass: five artifacts; final evidence lint pending. |
| Composer manifest | Pass. |

The upload runtime accepted exactly 10 MiB, rejected 10 MiB + 1 byte with
422, and rejected a request above 16 MiB with 413. Both rejections retained
the existing document binding.

Recorded commands:

```sh
PKP_QPDF_BINARY=/opt/homebrew/bin/qpdf php artisan test
./vendor/bin/pint --test
npm run build
npx playwright test --project=chromium
PKP_QPDF_BINARY=/path/to/qpdf \
  PKP_CHROMIUM_EXECUTABLE=/path/to/chromium \
  npx playwright test --config=playwright.pkp.config.js \
  --project=chromium
DB_CONNECTION=pgsql vendor/bin/phpunit \
  --configuration=phpunit.pkp-concurrency.xml \
  --filter=PkpWorkflowRaceTest
DB_CONNECTION=mysql vendor/bin/phpunit \
  --configuration=phpunit.pkp-concurrency.xml \
  --filter=PkpWorkflowRaceTest
PKP_STACK_BASE_URL=http://127.0.0.1:8085 vendor/bin/phpunit \
  --configuration=phpunit.pkp-concurrency.xml \
  --filter=PkpUploadEnvelopeTest
npx --no-install markdownlint-cli2 \
  plan/plan-monthly-pkp.md docs/ARCHITECTURE.md \
  CONTEXT.md docs/pkp-verification.md
composer validate --no-check-publish
```

The realized inventory in `docs/review/monthly-pkp-ac-evidence.md` maps all
90 criteria to exact test names and public seams. Its initial seven partial
compound facets received additional direct assertions. That functional
coverage is backed by the resumed baseline recovery evidence below. Local
Phase 5 gates are complete after the authorized repairs. Hosted CI and
production release are separate and have not been executed.

Exact values remain rational throughout. Regression probes now cover
500 distinct UI activities saved/reloaded, maximum deployment-database
counts/text/numbers, frozen microsecond timestamps, missing-source recovery,
dotted-key raw whitespace rejection, and actual PDF coordinate/text bounds.
PDF continuation rows retain all text without reducing fonts. Independent
Poppler extraction/render QA verifies maximum text and notes, headers, exact
fractions, and signature. Normal-motion desktop/mobile screenshots are
reported separately from deterministic tests that disable animation. The
normal-motion rerun passed in 5.9 seconds; both form screenshots are
readable. The application logo is unavailable in this test environment.

## PostgreSQL Bootstrap Compatibility

The repository's 2013 Revisionable migration calls `UUID()`, which PostgreSQL
does not provide by that name. The isolated PostgreSQL test database creates a
test-only `public.uuid()` wrapper around `gen_random_uuid()` before migration.
It does not alter application runtime behavior.

The real PostgreSQL migration run found two existing fresh-schema
incompatibilities: the Jabatan self-referencing key was added in the same
create statement, and `cuti_balance.pegawai_uuid` was a string referencing a
UUID key. The migration definitions now preserve the relationship and deletion
behavior through a separate self-FK step and matching UUID column. Fresh
PostgreSQL 16 and MySQL 8.0 migrations both apply successfully. No production
database was
migrated.

## Migration Recovery and Authorized Repair

On the isolated database `pkp_phase5_recovery_20261007`, additive PKP
up/down/up passes on both engines. Full baseline reset exposes two inherited
bugs: rank-history down drops `riwayat_pangkats` instead of the actual
`riwayat_pangkat` table; MySQL Cuti index down removes the remaining FK
support index. Their real reset commands exit 1. No production database
was used for these destructive recovery probes.

The user authorized the two-file repair through continuation on 2026-10-07.
The rank down method now drops the table it created. The MySQL index down
method preserves FK support by restoring its supporting index name when
necessary; it does not remove the FK. Both complete baseline fresh/reset/up
cycles now pass on the named disposable database on each engine. CI enforces
additive and baseline recovery; hosted CI has not been run in this session.

Resumed expected-failure and recovery logs are
`/tmp/pkp-phase5-resumed-{pg,mysql}-red.log` and
`/tmp/pkp-phase5-resumed-{pg,mysql}-{up,down,reup}.log`.

## Audit and Environment Notes

`composer audit --format=json --no-interaction` exits 1: five existing
advisories in Laravel Framework and Flysystem. One Laravel email-rule
advisory is high severity. Packagist metadata retrieval timed out and used
cached repository information, so metadata freshness is partly unverified.
`npm audit --json` exits 1: nine existing findings (seven high, two moderate,
zero critical), including Axios and the Tailwind dependency chain.
This remediation does not change dependency manifests/lockfiles or introduce
new dependencies. Existing high findings need a separate reachable-path and
pre-release disposition; no broad upgrades or release are authorized here.

No production deployment, staging, commit, push, or release was performed.
Only the named `simpeg-pkp-phase5` stack and two named qpdf test containers
were started. All five named stack containers are verified exited; both qpdf
test
containers exited with code 0. No volumes were removed. Unrelated
containers, test volumes, and the host MySQL service are preserved.

The dedicated PKP browser suite used the available Chrome for Testing binary
through `PKP_CHROMIUM_EXECUTABLE`. This host's Playwright 1208 browser download
timed out, so the suite used the already installed Chromium executable. The
test harness disables animation and transition durations to keep Alpine
dropdown interactions deterministic without changing assertions.

## Current Local Evidence Logs

- Full PHP: `/tmp/pkp-phase5-complete-functional.log`.
- PostgreSQL: `/tmp/pkp-phase5-pg-verified-races.log`.
- MySQL: `/tmp/pkp-phase5-mysql-verified-races.log`.
- Browser: `/tmp/pkp-phase5-verified-browser.log`.
- Base browser: `/tmp/pkp-phase5-verified-base-browser.log`.
- Envelope: `/tmp/pkp-phase5-verified-envelope.log`.
- Baseline failures: `/tmp/pkp-phase5-pg-down.log` and
  `/tmp/pkp-phase5-mysql-down.log`.
- Additive recovery: `/tmp/pkp-phase5-*-additive-{up,down,reup}.log`.
- Composer audit: `/tmp/pkp-phase5-composer-audit.json`.
- npm audit: `/tmp/pkp-phase5-npm-audit.json`.
- PDF maximum text/notes: `/tmp/pkp-phase5-max-text.pdf` and
  `/tmp/pkp-phase5-max-notes.pdf`.

## Resumed Final Gate Closure

After the authorized rollback changes, the full PHP suite passed again:
595 tests, 4,557 assertions, 87.61 seconds. Pint passed all 274 PHP files;
both edited migrations passed syntax checks. The five owned stack
containers are verified exited in
`/tmp/pkp-phase5-resumed-stack-state.json`; volumes remain intact.

Current resumed logs are `/tmp/pkp-phase5-resumed-full-php.log`,
`/tmp/pkp-phase5-resumed-{pg,mysql}-{up,down,reup}.log`, and
`/tmp/pkp-phase5-resumed-stop.log`. No hosted CI, production migration,
release, staging, or commit was performed.
