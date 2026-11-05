# Project Quality Constraints (`CONSTRAINTS.md`)

> **Project:** SIMPEG (Sistem Informasi Kepegawaian)
> **Last Calibrated On:** 2026-09-24

## 1. Quality Thresholds

| Metric | Target Threshold | Hard Floor (CI Failure) | Enforcement Tool |
| --- | --- | --- | --- |
| PHPUnit suite | 100% pass | 100% pass | `php artisan test` |
| Feature regression coverage | All changed behavior covered | No behavior changes without a focused regression test | PHPUnit |
| PHP formatting | No formatting violations in changed PHP files | 0 formatter errors | `./vendor/bin/pint --test` |
| E2E regression coverage | Relevant user-facing flows pass | No introduced Playwright failures | `npx playwright test --project=chromium` |
| Dependency security | 0 known high/critical vulnerabilities introduced | No new high/critical findings | `composer audit` and `npm audit` |
| Database safety | Migrations apply and rollback consistently where rollback is supported | No destructive or data-losing migration without explicit approval and a tested recovery plan | Laravel migrations / tests |

## 2. Verification Commands

Run the relevant focused test first, then the complete applicable quality gates:

```bash
php artisan test
./vendor/bin/pint --test
npx playwright test --project=chromium
composer audit
npm audit
```

For a production frontend build, run:

```bash
npm run build
```

Do not claim a check passed unless its command completed successfully. If a dependency audit is blocked by network or registry availability, report it as unverified rather than treating it as passed.

## 3. Floor-Guard Anti-Cheat Rules

Agents and contributors must not:

1. Add suppressions such as `@phpstan-ignore`, `@psalm-suppress`, `// @ts-ignore`, `eslint-disable`, or equivalent to hide genuine errors.
2. Remove or weaken existing PHPUnit assertions to make a test pass without an approved behavior change.
3. Add skipped tests (`markTestSkipped`, `@skip`, `.skip`, or equivalent) to bypass failures.
4. Write tautological tests that merely repeat the implementation's calculation instead of asserting independent expected outcomes.
5. Lower these constraints, disable CI checks, or change quality configuration to conceal a regression.
6. Introduce destructive database changes without explicit approval and a verified recovery plan.

Violations of these rules block completion until corrected.
