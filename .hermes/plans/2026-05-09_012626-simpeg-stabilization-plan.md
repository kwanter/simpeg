# Simpeg Stabilization and Quality Improvement Implementation Plan

> **For Hermes:** Use `simpeg-codebase` + `test-driven-development` + `subagent-driven-development` if this plan is executed task-by-task.

**Goal:** Memperbaiki temuan analisis Simpeg agar project bisa di-setup, dites, dibuild, dan divalidasi dengan workflow yang reliable.

**Architecture:** Kerjakan dari fondasi ke atas: bersihkan repository noise, pulihkan dependency/setup, perbaiki test harness, stabilkan aturan domain, lalu benahi E2E/CI/UI parity. Jangan refactor besar sebelum test suite sehat.

**Tech Stack:** Laravel 10, PHP 8.1+, PHPUnit 10, Blade, Vite 5, Tailwind, Alpine.js, Bootstrap CDN, Spatie Permission, Playwright.

---

## Current Context

Repo: `/Users/macbook/Developer/php/simpeg`

Temuan utama dari analisis:

1. `tests/SimpegTestCase.php` parse error di line 24: `unexpected variable "$this", expecting "function"`.
2. Local dependencies belum siap: `vendor/autoload.php` tidak ada, `node_modules` tidak ada.
3. `npm run build` gagal karena `vite: command not found`.
4. `php artisan ...` gagal karena `vendor/autoload.php` missing.
5. Playwright masih placeholder, test `e2e/example.spec.js` mengarah ke `https://playwright.dev/`, bukan aplikasi Simpeg.
6. `.github/workflows/playwright.yml` hanya Node/Playwright; belum install PHP/composer, migrate DB, atau start Laravel app.
7. Navigation desktop/mobile tidak parity: desktop punya role/link lebih lengkap, mobile tidak.
8. `.DS_Store`, `.atlas/`, `.serena/` muncul sebagai noise di git status.
9. README masih default Laravel, belum menjelaskan setup/domain Simpeg.
10. `CutiController.php` besar/high-risk; jangan refactor sebelum test suite sehat.

## Non-Goals untuk Plan Ini

- Tidak mengubah business rule cuti/izin kecuali diperlukan untuk memperbaiki test yang jelas salah.
- Tidak melakukan refactor besar `CutiController.php` dulu.
- Tidak mengganti schema UUID/NIP relationship.
- Tidak menghapus `.atlas/` atau `.serena/` secara permanen; cukup exclude dari git tracking.

---

## Phase 0 — Safety Baseline

### Task 0.1: Confirm repo and branch state

**Objective:** Pastikan perubahan dilakukan di repo Simpeg dan kondisi git dipahami.

**Files:** Tidak ada perubahan.

**Steps:**

1. Run:
   ```bash
   cd /Users/macbook/Developer/php/simpeg
   pwd
   git rev-parse --show-toplevel
   git branch --show-current
   git status --short
   ```
2. Expected:
   - root adalah `/Users/macbook/Developer/php/simpeg`
   - status menunjukkan noise `.DS_Store`, `.atlas/`, `.serena/` jika belum dibersihkan.

**Commit:** Tidak perlu.

### Task 0.2: Add local tooling noise to git ignore

**Objective:** Hindari `.DS_Store`, `.atlas/`, `.serena/`, dan output Playwright/report masuk ke commit.

**Files:**
- Modify: `.gitignore`

**Steps:**

1. Tambahkan entries jika belum ada:
   ```gitignore
   # macOS
   .DS_Store

   # Hermes/AI local tooling
   .atlas/
   .serena/
   .hermes/tmp/

   # Playwright output
   playwright-report/
   test-results/
   ```
2. Run:
   ```bash
   git status --short
   ```
3. Expected:
   - untracked `.atlas/` dan `.serena/` tidak muncul lagi.
   - `.DS_Store` yang sudah tracked mungkin masih muncul; lanjut Task 0.3.

**Commit:**
```bash
git add .gitignore
git commit -m "chore: ignore local tooling and report artifacts"
```

### Task 0.3: Remove tracked `.DS_Store` files from git index only

**Objective:** Bersihkan `.DS_Store` dari tracking tanpa menghapus file lokal secara paksa.

**Files:**
- Git index only for `*.DS_Store`

**Steps:**

1. List tracked `.DS_Store`:
   ```bash
   git ls-files | grep '\.DS_Store$'
   ```
2. Jika ada output, run:
   ```bash
   git ls-files | grep '\.DS_Store$' | xargs git rm --cached
   ```
3. Run:
   ```bash
   git status --short
   ```
4. Expected: `.DS_Store` tampil sebagai deleted from index untuk commit cleanup, bukan app-code edit.

**Commit:**
```bash
git commit -m "chore: stop tracking macOS metadata files"
```

---

## Phase 1 — Restore Local Dependency Baseline

### Task 1.1: Install Composer dependencies

**Objective:** Membuat `php artisan`, PHPUnit, Pint, dan package discovery bisa berjalan.

**Files:**
- Expected generated: `vendor/` (ignored)
- Possible modified: `composer.lock` only if it is missing/outdated and composer updates lock unexpectedly.

**Steps:**

1. Run:
   ```bash
   composer install
   ```
2. If composer reports lock mismatch, do not run `composer update` automatically. Inspect first.
3. Verify:
   ```bash
   test -f vendor/autoload.php && echo "vendor ready"
   php artisan --version
   ```

**Expected:** `vendor ready` and Laravel version prints.

**Commit:** Usually none unless lockfile legitimately changes.

### Task 1.2: Install Node dependencies

**Objective:** Membuat Vite/Playwright commands available.

**Files:**
- Expected generated: `node_modules/` (ignored)
- Possible modified: `package-lock.json` if lockfile exists or is generated.

**Steps:**

1. Prefer reproducible install if lockfile exists:
   ```bash
   if [ -f package-lock.json ]; then npm ci; else npm install; fi
   ```
2. Verify:
   ```bash
   npx vite --version
   npm run build
   ```

**Expected:** `npm run build` passes.

**Commit:** Commit `package-lock.json` only if project policy is to track it.

### Task 1.3: Prepare `.env` for local test/dev

**Objective:** Ensure artisan commands and local server can run.

**Files:**
- Create local only: `.env` from `.env.example`

**Steps:**

1. Run:
   ```bash
   test -f .env || cp .env.example .env
   php artisan key:generate
   ```
2. For local testing, keep PHPUnit using `phpunit.xml` SQLite in-memory. Do not put secrets in repo.
3. Verify:
   ```bash
   php artisan config:clear
   php artisan route:list --compact
   ```

**Commit:** None; `.env` must remain ignored.

---

## Phase 2 — Fix PHP Test Harness

### Task 2.1: Repair `tests/SimpegTestCase.php` syntax only

**Objective:** Make test helper parse before changing any domain behavior.

**Files:**
- Modify: `tests/SimpegTestCase.php`

**Known issue:** File appears corrupted/duplicated: duplicated `createUserWithRole()`, duplicated permission arrays, extra brace around `setUp()`, and `$nip` used before assignment in one duplicated block.

**Target shape:**

```php
<?php

namespace Tests;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

abstract class SimpegTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seedCutiPermissions();
    }

    protected function createUserWithRole(string $roleName, array $permissions = [], ?string $nip = null): User
    {
        $nip ??= fake()->unique()->numerify('199#########');

        $role = Role::firstOrCreate(
            ['name' => $roleName, 'guard_name' => 'web'],
            ['uuid' => (string) \Illuminate\Support\Str::uuid()]
        );

        foreach ($permissions as $permissionName) {
            $permission = Permission::firstOrCreate(
                ['name' => $permissionName, 'guard_name' => 'web'],
                ['uuid' => (string) \Illuminate\Support\Str::uuid()]
            );
            $role->givePermissionTo($permission);
        }

        $user = User::factory()->create(['nip' => $nip]);
        $user->assignRole($role);

        return $user;
    }

    protected function seedCutiPermissions(): void
    {
        foreach ([
            'view cuti',
            'create cuti',
            'update cuti',
            'delete cuti',
            'verifikasi cuti',
            'pimpinan cuti',
            'atasan pimpinan cuti',
            'view izin',
            'create izin',
            'update izin',
            'delete izin',
            'verifikasi izin',
            'view hari libur',
        ] as $permissionName) {
            Permission::firstOrCreate(
                ['name' => $permissionName, 'guard_name' => 'web'],
                ['uuid' => (string) \Illuminate\Support\Str::uuid()]
            );
        }
    }
}
```

**Important:** Adjust exact permissions after checking seeders/tests; do not remove permissions required by existing tests.

**Verification:**

```bash
php -l tests/SimpegTestCase.php
```

Expected: `No syntax errors detected`.

**Commit:**
```bash
git add tests/SimpegTestCase.php
git commit -m "test: repair Simpeg test base class syntax"
```

### Task 2.2: Run focused test discovery after syntax repair

**Objective:** Reveal real failing tests after parser is healthy.

**Files:** No intended code changes.

**Steps:**

1. Run:
   ```bash
   php artisan test --filter=SecureHeadersMiddlewareTest
   php artisan test --filter=WorkdayServiceTest
   php artisan test --filter=CutiPolicyTest
   php artisan test --filter=IzinPolicyTest
   ```
2. Categorize failures:
   - migration/schema mismatch
   - factory missing fields
   - role/permission seeding issue
   - actual policy/business logic mismatch

**Expected:** Some failures may appear after parse fix. Do not fix all at once; create one task per failure cluster.

**Commit:** None unless changes are made.

### Task 2.3: Normalize test helper role/permission setup

**Objective:** Make policy tests deterministic with Spatie Permission and UUID custom Role/Permission models.

**Files:**
- Modify: `tests/SimpegTestCase.php`
- Possibly modify: `tests/Unit/Policies/CutiPolicyTest.php`
- Possibly modify: `tests/Unit/Policies/IzinPolicyTest.php`

**Steps:**

1. Ensure each test starts with:
   ```php
   app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
   ```
2. Ensure role and permission creation uses `guard_name => 'web'`.
3. Ensure custom `Role` and `Permission` models receive UUID if automatic creation does not do it consistently.
4. Re-run:
   ```bash
   php artisan test --filter=CutiPolicyTest
   php artisan test --filter=IzinPolicyTest
   ```

**Commit:**
```bash
git add tests/SimpegTestCase.php tests/Unit/Policies
 git commit -m "test: stabilize role permission policy fixtures"
```

---

## Phase 3 — Verify and Correct Workday/Holiday Behavior

### Task 3.1: Confirm intended weekend behavior

**Objective:** Resolve ambiguity: `WorkdayService` name suggests weekdays, but implementation relies on `HariLibur::getHariLiburByDateRange()` which itself includes weekends.

**Files:**
- Read: `app/Services/WorkdayService.php`
- Read: `app/Models/HariLibur.php`
- Read: `tests/Unit/Services/WorkdayServiceTest.php`

**Steps:**

1. Run:
   ```bash
   php artisan test --filter=WorkdayServiceTest
   ```
2. If failing, inspect whether tests expect weekends excluded.
3. Current likely intended behavior: weekends should be excluded because `HariLibur::getHariLiburByDateRange()` returns Saturday/Sunday plus DB holidays.

**Commit:** None if no change.

### Task 3.2: Make workday behavior explicit and covered

**Objective:** Reduce future confusion by making weekend exclusion explicit either in service code or tests/docs.

**Files:**
- Modify: `app/Services/WorkdayService.php` if needed
- Modify: `tests/Unit/Services/WorkdayServiceTest.php`

**Preferred approach:** If `HariLibur` already returns weekends, keep behavior but add comments/tests that document it. Only change code if test proves bug.

**Verification:**

```bash
php artisan test --filter=WorkdayServiceTest
```

**Commit:**
```bash
git add app/Services/WorkdayService.php tests/Unit/Services/WorkdayServiceTest.php
 git commit -m "test: document workday holiday calculation behavior"
```

---

## Phase 4 — Fix Frontend Build and Navigation Parity

### Task 4.1: Ensure Vite build passes

**Objective:** Validate current Blade/Vite frontend compiles.

**Files:** No intended code changes unless build exposes asset issue.

**Steps:**

1. Run after `npm install`:
   ```bash
   npm run build
   ```
2. If failing, fix only reported asset errors.

**Commit:** Only if code changes.

### Task 4.2: Align mobile navigation with desktop navigation

**Objective:** Fix navigation parity gap for roles and modules.

**Files:**
- Modify: `resources/views/layouts/navigation.blade.php`

**Current issue:** Desktop nav includes privileged checks for `atasan-pimpinan`, Izin, Hari Libur. Mobile nav only exposes fewer roles/links.

**Proposed approach:** Mirror desktop conditions in responsive menu.

**Checklist:**

- Mobile Users visible for `super-admin|admin`.
- Mobile Pegawai and Cuti visible for `super-admin|admin|atasan-pimpinan|pimpinan|verifikator`.
- Mobile Izin shown with `@can('view izin')`.
- Mobile Hari Libur shown with `@can('view hari libur')`.

**Verification:**

1. Run:
   ```bash
   npm run build
   ```
2. Manual/browser check at mobile width after app starts:
   - login as admin
   - open hamburger menu
   - verify same functional modules are available as desktop.

**Commit:**
```bash
git add resources/views/layouts/navigation.blade.php
 git commit -m "fix: align responsive navigation with desktop menu"
```

---

## Phase 5 — Replace Placeholder Playwright with App E2E Scaffold

### Task 5.1: Update Playwright config for Laravel app

**Objective:** Make `npx playwright test` target Simpeg instead of external docs site.

**Files:**
- Modify: `playwright.config.js`

**Proposed config changes:**

```js
use: {
  baseURL: process.env.APP_URL || 'http://127.0.0.1:8000',
  trace: 'on-first-retry',
},
webServer: {
  command: 'php artisan serve --host=127.0.0.1 --port=8000',
  url: 'http://127.0.0.1:8000',
  reuseExistingServer: !process.env.CI,
  timeout: 120 * 1000,
},
```

**Caution:** This requires composer deps and `.env` ready.

**Verification:**

```bash
npx playwright test --list
```

**Commit:**
```bash
git add playwright.config.js
git commit -m "test: configure Playwright for Laravel app"
```

### Task 5.2: Replace external demo E2E test

**Objective:** Remove dependency on `playwright.dev`; add minimal app smoke tests.

**Files:**
- Modify or replace: `e2e/example.spec.js`

**Proposed tests:**

```js
// @ts-check
import { test, expect } from '@playwright/test';

test('guest can see login page', async ({ page }) => {
  await page.goto('/login');
  await expect(page).toHaveTitle(/Sistem Informasi Kepegawaian|Laravel|Simpeg/i);
  await expect(page.getByLabel(/email|nip/i)).toBeVisible();
  await expect(page.getByLabel(/password/i)).toBeVisible();
});

test('guest is redirected from dashboard to login', async ({ page }) => {
  await page.goto('/dashboard');
  await expect(page).toHaveURL(/\/login/);
});
```

**Verification:**

```bash
npx playwright test --project=chromium
```

**Commit:**
```bash
git add e2e/example.spec.js
 git commit -m "test: replace placeholder Playwright tests with Simpeg smoke tests"
```

---

## Phase 6 — Improve CI Coverage

### Task 6.1: Add Laravel CI workflow

**Objective:** CI should install PHP deps, run lint/syntax/tests, and build assets.

**Files:**
- Create: `.github/workflows/laravel.yml`

**Proposed workflow outline:**

```yaml
name: Laravel Tests

on:
  push:
    branches: [main, master]
  pull_request:
    branches: [main, master]

jobs:
  laravel:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, dom, fileinfo, sqlite, pdo_sqlite
          coverage: none

      - uses: actions/setup-node@v4
        with:
          node-version: lts/*
          cache: npm

      - name: Install Composer dependencies
        run: composer install --no-interaction --prefer-dist --no-progress

      - name: Prepare environment
        run: |
          cp .env.example .env
          php artisan key:generate

      - name: PHP syntax check
        run: |
          find app routes database tests config -name '*.php' -print0 | xargs -0 -n1 php -l

      - name: Run PHPUnit
        run: php artisan test

      - name: Install Node dependencies
        run: npm ci

      - name: Build assets
        run: npm run build
```

**Verification:**

- Commit workflow.
- Push/PR should run CI.

**Commit:**
```bash
git add .github/workflows/laravel.yml
 git commit -m "ci: add Laravel test and asset build workflow"
```

### Task 6.2: Update Playwright CI workflow to start Laravel correctly

**Objective:** Playwright CI should test the app, not only external placeholder tests.

**Files:**
- Modify: `.github/workflows/playwright.yml`

**Required additions:**

- Setup PHP.
- Composer install.
- Copy `.env.example` and key generate.
- Use SQLite file or in-memory compatible app env.
- Run migrations/seed if E2E requires login users.
- Install Node deps.
- Run Playwright with webServer config.

**Verification:**

- CI runs `npx playwright test` against local Laravel server.

**Commit:**
```bash
git add .github/workflows/playwright.yml
 git commit -m "ci: run Playwright against local Laravel app"
```

---

## Phase 7 — Project Documentation

### Task 7.1: Replace default README with Simpeg setup guide

**Objective:** README should document actual project, setup, test, and known domain flows.

**Files:**
- Modify: `README.md`

**README sections:**

```md
# Simpeg

## Overview
## Tech Stack
## Local Setup
## Database and Seeders
## Roles and Permissions
## Main Modules
## Cuti Workflow
## Izin Workflow
## Testing
## Playwright E2E
## Common Troubleshooting
```

**Include commands:**

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm run build
php artisan test
```

**Verification:**

- README contains no credentials.
- README references correct path names and workflows.

**Commit:**
```bash
git add README.md
 git commit -m "docs: document Simpeg setup and workflows"
```

---

## Phase 8 — Optional Later Refactor After Tests Are Green

### Task 8.1: Extract Cuti validation/business rules into service classes

**Objective:** Reduce risk in `CutiController.php` after tests are reliable.

**Files likely:**
- Create: `app/Services/Cuti/CutiDurationService.php`
- Create: `app/Services/Cuti/CutiBalanceService.php`
- Create: `app/Services/Cuti/CutiValidationService.php`
- Modify: `app/Http/Controllers/CutiController.php`
- Add tests under: `tests/Unit/Services/Cuti/`

**Precondition:**

- `php artisan test --filter=Cuti` green.
- `php artisan test --filter=WorkdayServiceTest` green.

**Do not start this before Phases 0-7 are complete.**

---

## Final Verification Matrix

After all required phases:

```bash
cd /Users/macbook/Developer/php/simpeg

# Repo cleanliness
git status --short

# PHP syntax
find app routes database tests config -name '*.php' -print0 | xargs -0 -n1 php -l

# Laravel health
php artisan route:list --compact

# Focused tests
php artisan test --filter=SecureHeadersMiddlewareTest
php artisan test --filter=WorkdayServiceTest
php artisan test --filter=CutiPolicyTest
php artisan test --filter=IzinPolicyTest

# Full PHP tests
php artisan test

# Formatting/lint if available
./vendor/bin/pint --test

# Frontend build
npm run build

# E2E smoke
npx playwright test --project=chromium
```

Expected final state:

- No PHP parse errors.
- `php artisan` commands run.
- `npm run build` passes.
- Policy/workday tests pass.
- Playwright targets Simpeg app, not `playwright.dev`.
- Git status contains only intentional changes.
- README explains project setup and workflows.

## Recommended Execution Order

1. Phase 0: Git/tooling cleanup.
2. Phase 1: Install local dependencies.
3. Phase 2: Repair `tests/SimpegTestCase.php` and stabilize policy tests.
4. Phase 3: Verify workday behavior.
5. Phase 4: Build frontend and fix nav parity.
6. Phase 5: Replace Playwright placeholder.
7. Phase 6: Add/repair CI.
8. Phase 7: Update README.
9. Phase 8: Optional refactor only after test suite green.

## Risks and Open Questions

- Need decide whether to commit `package-lock.json` if it is newly generated.
- Need inspect actual existing `tests/SimpegTestCase.php` before applying target shape; permissions must match seeders/tests.
- Need decide whether E2E tests should create users via seeder, test DB, or bypass login for smoke tests.
- Need verify GitHub branch target: workflow currently uses `main, master`; keep both unless repository standard says otherwise.
- Need decide whether CI should use PHP 8.1, 8.2, or 8.3. Composer allows `^8.1`; local analysis used PHP 8.3.
