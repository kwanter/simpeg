# Simpeg Production Readiness Audit Plan

Created: 2026-05-09 12:00 WITA
Repo: `/Users/macbook/Developer/php/simpeg`
Mode: planning only; no app code changes in this step.

## Goal

Run a whole-project audit before production deployment to find and fix:

1. Functional bugs and regressions.
2. Security flaws: authz/authn gaps, upload issues, exposed secrets, weak headers, unsafe config, dependency CVEs.
3. Data integrity risks: UUID/NIP relationships, migrations, seeders, indexes, soft deletes, leave/izin workflow rules.
4. Deployment blockers: Docker production build, env config, cache/route config, assets, storage permissions, CI gaps.
5. Browser/UI issues in critical workflows.

Final output after execution should be:

- Prioritized issue list: Critical / High / Medium / Low.
- Fix commits or patch set for approved fixes.
- Passing verification checklist.
- Production deployment go/no-go note.

## Current context

- Active repo confirmed: `/Users/macbook/Developer/php/simpeg`.
- Branch: `main`, tracking `origin/main`.
- Current worktree is not clean. Existing local changes include CI, README, tests, Playwright config, factories, revisions migration, navigation, generated assets, deleted `.DS_Store`, and untracked `.hermes/`.
- Known stable snapshot from project skill: Laravel 10 + Blade + Vite + Spatie Permission + UUID domain models.
- Previous full suite status in memory: 107 PHP tests passing and 2 Playwright E2E tests passing, but we must re-run because current worktree changed.
- Qdrant code search attempt failed with vector-name mismatch: `Not existing vector name error: fast-nomic-embed-text-v1.5-q`. Treat qdrant-code as unavailable until config/index is fixed or collection rebuilt.
- AtlasMemory project context is available and identifies high-risk files: `CutiController`, `IzinController`, `SecureHeadersMiddleware`, `PegawaiController`, `RoleController`, `Kernel`, auth/session/sanctum config, role/permission seeders.

## Tooling strategy: use everything available

Use these tools in the execution phase:

1. Serena MCP
   - Symbol search and targeted code reading/editing.
   - Focus on controllers, policies, middleware, models, route definitions.

2. AtlasMemory
   - `build_context(mode="task")` for each audit area.
   - `smart_diff` for current local change risk.
   - `analyze_impact` before changing core symbols.
   - `log_decision` after meaningful fixes.

3. qdrant-code
   - First fix/verify collection configuration if needed.
   - Use semantic search for hidden duplicated patterns once working.

4. Context7
   - Query latest docs for Laravel 10, Spatie Permission, Sanctum, DomPDF, Intervention Image, Vite/Playwright when validating security or deployment behavior.

5. Git MCP / terminal git
   - Audit diff, recent commits, touched files.
   - Keep fixes atomic and reviewable.

6. FS MCP / read_file / search_files
   - Safe filesystem inspection.
   - Search dangerous patterns and route/controller surfaces.

7. Browser tools: Playwright + Chrome DevTools MCP
   - E2E critical flows and manual browser verification.
   - Console/network/error inspection.

8. Delegate subagents
   - Parallel independent review tracks: security, domain logic, deployment, UI/E2E, dependency/config.
   - Fresh-context reviewer for any fixes before final commit.

9. GitHub MCP / gh if remote issues or CI are involved
   - Inspect workflows and optionally create issues/PR later if requested.

## Phase 0 — Safety and baseline snapshot

Objective: avoid mixing audit findings with unknown local changes.

Steps:

1. Confirm repo and branch:
   - `pwd`
   - `git rev-parse --show-toplevel`
   - `git status --short --branch`
   - `git log --oneline -10`

2. Capture full current diff:
   - `git diff --stat`
   - `git diff -- . ':(exclude).DS_Store' ':(exclude)public/build/*'`

3. Classify current local changes:
   - App code changes.
   - Test/CI changes.
   - Docs changes.
   - Generated assets.
   - Local noise: `.DS_Store`, `.atlas/`, `.serena/`, `.hermes/`.

4. Decide before fixing:
   - Either audit against current dirty working tree.
   - Or create a clean audit branch and stash/noise-isolate irrelevant changes.

Suggested branch:

```bash
git switch -c audit/production-readiness-2026-05-09
```

Do not commit `.DS_Store`, `.atlas/`, `.serena/`, or plan files unless explicitly desired.

## Phase 1 — Project inventory and attack surface map

Objective: map every major surface before looking for flaws.

Read/inspect:

- `routes/web.php`
- `routes/auth.php`
- `app/Http/Kernel.php`
- `app/Http/Middleware/*`
- `app/Http/Controllers/*Controller.php`
- `app/Policies/*.php`
- `app/Models/*.php`
- `database/migrations/*.php`
- `database/seeders/*.php`
- `config/app.php`
- `config/auth.php`
- `config/session.php`
- `config/sanctum.php`
- `config/filesystems.php`
- `config/database.php`
- Docker files and GitHub workflows.

Produce an attack-surface table:

| Area | Files | Risks to check |
| --- | --- | --- |
| Auth/login/profile | `routes/auth.php`, auth controllers, requests, profile views | disabled registration, status=1 login, UUID route params, password update/delete-account behavior |
| Authorization | `routes/web.php`, policies, controllers, seeders | missing middleware, role bypass, policy mismatch, direct object access |
| Cuti workflow | `CutiController`, `CutiPolicy`, models, views | balance corruption, status transition bypass, ownership leaks, upload/PDF issues |
| Izin workflow | `IzinController`, `IzinPolicy`, models, views | verifier sequence bypass, incorrect owner filtering, upload/PDF issues |
| Pegawai/users/roles | controllers, models, seeders | privilege escalation, mass assignment, soft-delete behavior, NIP/UUID mismatch |
| File uploads | controllers + `config/filesystems.php` | MIME validation, extension spoofing, public exposure, old-file deletion, path traversal |
| Security headers/session | middleware + config | CSP/HSTS/session secure flags, cookie settings, proxy HTTPS behavior |
| Deployment | Docker, `.env.example`, CI | prod build failures, storage perms, missing app key, APP_DEBUG, route/config cache |

## Phase 2 — Automated static and dependency checks

Objective: quickly catch known bad patterns and vulnerable packages.

Commands/checks:

1. Composer dependency audit:

```bash
composer audit
composer outdated --direct
```

2. NPM dependency audit:

```bash
npm audit --omit=dev
npm outdated --depth=0
```

3. Laravel security/config checks:

```bash
php artisan about
php artisan route:list --columns=method,uri,name,action,middleware
php artisan config:show app
php artisan config:show session
```

4. Search dangerous PHP/Blade patterns:

- Hardcoded secrets:
  - `APP_KEY=`
  - `password =>`
  - `token`
  - `secret`
  - `api_key`
- Debug leftovers:
  - `dd(`, `dump(`, `ray(`, `var_dump`, `print_r`
- Unsafe execution/deserialization:
  - `eval(`, `exec(`, `shell_exec`, `system(`, `unserialize(`
- Raw SQL and possible injection:
  - `DB::raw`, `whereRaw`, `orderByRaw`, `selectRaw`, string concatenated queries
- XSS risk:
  - `{!!` in Blade
  - inline JS consuming user data
- Upload risks:
  - `storeAs`, `move`, `getClientOriginalName`, weak `mimes` validation
- Open redirects / URL trust:
  - `redirect($request`, `redirect()->away`, `url()->previous()`

5. Optional static analyzers if installable:

```bash
./vendor/bin/pint --test
# If PHPStan/Larastan is not installed, propose adding it later instead of forcing in this audit.
```

Deliverable: `audit-findings-static.md` with every finding and file/line evidence.

## Phase 3 — Security deep audit

Objective: manually verify high-risk code paths, not only grep results.

### 3.1 Authentication and session

Check:

- `LoginRequest` email-or-NIP logic.
- Status-based login restriction (`status = 1`).
- Throttling/rate limiting.
- Password reset/email verification routes with UUID user key.
- Registration route disabled as intended.
- `SESSION_SECURE_COOKIE`, `SESSION_HTTP_ONLY`, `SESSION_SAME_SITE` production readiness.
- Trusted proxies / HTTPS detection for production reverse proxy.

Tests to add if missing:

- Inactive user cannot login.
- User can login with email and NIP.
- `/register` returns 404.
- Password reset/email verification does not assume numeric ID.

### 3.2 Authorization / IDOR

Check every controller action for:

- Route middleware present.
- Policy or permission check present.
- Query is scoped to current user/role when user is not admin.
- Direct UUID route access cannot view/update/delete another user's data.

High-priority files:

- `app/Http/Controllers/CutiController.php`
- `app/Http/Controllers/IzinController.php`
- `app/Http/Controllers/PegawaiController.php`
- `app/Http/Controllers/UserController.php` if present
- `app/Http/Controllers/RoleController.php`
- `app/Policies/CutiPolicy.php`
- `app/Policies/IzinPolicy.php`
- `routes/web.php`

Tests to add:

- Regular user cannot view/edit/delete another user's cuti.
- Regular user cannot view/edit/delete another user's izin.
- Verifikator/pimpinan/atasan-pimpinan can only act in allowed workflow stage.
- Non-admin cannot access role/user/permission management.

### 3.3 Upload and public file safety

Check upload endpoints:

- Pegawai photo upload.
- Cuti document upload.
- Izin document upload.

Audit:

- Required max file size.
- MIME + extension validation.
- UUID/random filenames, no original name trust.
- Stored under intended disk only.
- Public access is acceptable for document sensitivity; if not, move to private storage with authorized download route.
- Old file deletion is safe and cannot delete arbitrary paths.
- PDF generation cannot load unsafe remote resources.

Tests to add:

- Reject PHP disguised upload.
- Reject oversized file.
- Old document replacement deletes only expected file.

### 3.4 XSS / Blade output

Check all `{!! ... !!}` occurrences and inline JS.

Rules:

- Use escaped `{{ }}` for user-controlled fields.
- Only allow raw HTML for trusted static content.
- Avoid injecting raw DB strings into script contexts.

Critical user-controlled fields:

- Pegawai names, addresses, phone/email, NIP.
- Cuti/izin reasons and descriptions.
- Role/permission names if admin-editable.

### 3.5 CSRF, method spoofing, destructive actions

Check all forms:

- `@csrf` present.
- `@method('DELETE'|'PUT'|'PATCH')` correct.
- Destructive actions require POST/DELETE, not GET.
- Delete-account/user/delete-cuti/delete-izin confirmation behavior is safe.

### 3.6 Security headers / CSP

Check:

- `SecureHeadersMiddleware.php`
- `CheckSecurityHeaders.php`
- `app/Http/Kernel.php`
- Nginx/Apache configs.

Verify headers in browser/HTTP response:

- `X-Frame-Options`
- `X-Content-Type-Options`
- `Referrer-Policy`
- `Permissions-Policy`
- `Strict-Transport-Security` only in HTTPS production context.
- CSP compatibility with Bootstrap/jQuery/Font Awesome CDNs, Vite assets, inline scripts.

## Phase 4 — Domain logic / data integrity audit

Objective: find bugs that tests may miss.

### 4.1 Cuti

Review:

- Date validation: start <= end, no negative/zero day logic.
- Workday calculation and holiday behavior.
- Annual balance updates and race conditions.
- Cuti Besar 5-year eligibility and max days.
- Conflict rules between Cuti Tahunan and Cuti Besar.
- Status transitions:
  - Pending -> Disetujui Verifikator -> Disetujui Pimpinan -> Disetujui Atasan Pimpinan
- Rejection states if any.
- `no_surat_cuti` edit rules.
- PDF generation authorization.
- NIP-to-Pegawai lookup failure handling.

Add tests for any uncovered rule.

### 4.2 Izin

Review:

- Date/time validation.
- `jumlah_hari` calculation.
- Verification sequence: atasan first, then pimpinan.
- `status`, `verifikasi_atasan`, `verifikasi_pimpinan` consistency.
- `no_surat_izin` edit rules.
- PDF generation authorization.
- Role-scoped index visibility.

Add tests for any uncovered rule.

### 4.3 Pegawai/User/Role

Review:

- User primary key UUID handling everywhere.
- Pegawai linked by NIP, not user id.
- Soft delete behavior and cascading/relationship assumptions.
- Mass assignment fillables on all models.
- Role/permission assignment cannot remove last super-admin or escalate by non-super-admin.
- Seeder creates all permissions used by routes/views/policies.

### 4.4 Database constraints and indexes

Review migrations for:

- Missing unique constraints: NIP, email, UUIDs.
- Missing foreign key constraints where safe.
- Indexes on `pegawai_uuid`, `nip`, status/filter columns.
- Nullable fields that should be required.
- Production MySQL compatibility for SQLite-tested migrations.

Run:

```bash
php artisan migrate:fresh --seed --env=testing
```

If possible, also test MySQL through Docker, because SQLite can hide MySQL-specific issues.

## Phase 5 — Test suite and quality gate

Objective: establish trust before fixing and after fixing.

Baseline commands:

```bash
composer install
npm ci
php artisan test
./vendor/bin/pint --test
npm run build
npx playwright test --project=chromium
```

If failures occur:

1. Follow systematic debugging.
2. Reproduce the specific failure.
3. Identify root cause before fixing.
4. Add/adjust regression tests.
5. Re-run focused test then full suite.

Coverage expansion targets:

- Feature tests for Cuti controller happy/denied paths.
- Feature tests for Izin controller happy/denied paths.
- Feature tests for upload validation.
- Feature tests for auth/session behavior.
- Browser E2E for login, dashboard navigation, create cuti, create izin, and unauthorized access redirects.

## Phase 6 — Browser and UX production smoke tests

Objective: verify critical flows in real browser, not only PHPUnit.

Use Playwright and Chrome DevTools MCP.

Critical paths:

1. Guest:
   - Login page loads.
   - Protected route redirects to login.

2. User:
   - Login by email and by NIP.
   - Dashboard loads with no console errors.
   - Create cuti request.
   - Create izin request.
   - Cannot access admin pages.

3. Verifikator/Pimpinan/Atasan:
   - Navigation links visible correctly desktop and mobile.
   - Can see assigned verification lists.
   - Can approve only allowed stage.

4. Admin/Super-admin:
   - Manage user/pegawai/role/permission.
   - Generate PDFs.
   - Upload photo/document.

Check:

- Console errors.
- Failed network requests.
- 500/403/419 errors.
- Mobile navigation parity.
- PDF routes and downloaded files.

## Phase 7 — Deployment readiness audit

Objective: ensure production container/env works before deploy.

Inspect:

- `.env.example`
- `docker/common/php-fpm/Dockerfile`
- `docker/production/php-fpm/entrypoint.sh`
- `docker/production/nginx/Dockerfile`
- `docker/production/nginx/nginx.conf`
- `.dockerignore`
- `docker-compose.yml`
- `docker-compose.dev.yml`
- GitHub workflows.

Check:

- `APP_ENV=production`, `APP_DEBUG=false` documented and enforced.
- No secrets in repo.
- `APP_KEY` generation documented, not committed.
- `storage` and `bootstrap/cache` permissions.
- `php artisan storage:link` or equivalent in deployment.
- `config:cache`, `route:cache`, `view:cache` compatibility.
- Migrations run intentionally and safely.
- Nginx serves only `public/`.
- PHP-FPM container has required extensions.
- Assets built and manifest matches.
- Healthcheck/logging/backups configured.
- HTTPS/proxy headers/session secure cookie assumptions documented.

Commands:

```bash
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize:clear

docker build -f docker/common/php-fpm/Dockerfile --target production -t simpeg-php-fpm:audit .
docker build -f docker/production/nginx/Dockerfile -t simpeg-nginx:audit .
```

If Docker is available, run a production-like stack with MySQL and execute smoke tests.

## Phase 8 — Parallel specialist reviews

Spawn independent subagents after baseline context is collected.

### Subagent A — Security reviewer

Scope:

- Auth/session/security headers.
- Controller authz/IDOR.
- Upload and XSS.
- Dependency audit results.

Deliverable:

- JSON/list findings with severity, file, line, exploit scenario, proposed fix, test recommendation.

### Subagent B — Domain logic reviewer

Scope:

- Cuti/Izin workflows.
- Workday/holiday/balance calculations.
- UUID/NIP/model relationships.
- Migration constraints and seeders.

Deliverable:

- Bugs and missing tests.

### Subagent C — Deployment reviewer

Scope:

- Docker, CI, `.env.example`, production caching, MySQL compatibility, storage, assets.

Deliverable:

- Deployment blockers and commands to verify.

### Subagent D — UI/E2E reviewer

Scope:

- Browser flows, mobile nav, PDF, forms, console/network failures.

Deliverable:

- E2E failures, screenshots/paths if applicable, reproduction steps.

Parent agent merges duplicate findings and verifies the highest-risk claims with direct file reads/tests before fixing.

## Phase 9 — Fix execution rules

For each confirmed issue:

1. Create/confirm failing test where practical.
2. Fix root cause only.
3. Do not bundle unrelated refactors.
4. Run focused verification.
5. Run full relevant suite.
6. Run independent reviewer on final diff before commit.

Fix priority:

1. Critical security: auth bypass, privilege escalation, IDOR, file upload RCE, secrets exposure.
2. High deployment blockers: prod build fails, migrations fail on MySQL, APP_DEBUG/secrets, broken assets.
3. High data integrity: leave balance corruption, invalid status transitions, relationship mismatches.
4. Medium security hardening: headers/session/CSP gaps, dependency upgrades.
5. Medium/Low UX and maintainability issues.

Commit strategy:

- One commit per logical fix group.
- Suggested prefixes:
  - `fix(security): ...`
  - `fix(deploy): ...`
  - `fix(cuti): ...`
  - `fix(izin): ...`
  - `test: ...`

## Phase 10 — Final production gate

Before saying ready for production, all must pass:

```bash
php artisan test
./vendor/bin/pint --test
npm run build
npx playwright test --project=chromium
composer audit
npm audit --omit=dev
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize:clear
```

If Docker is in scope:

```bash
docker build -f docker/common/php-fpm/Dockerfile --target production -t simpeg-php-fpm:audit .
docker build -f docker/production/nginx/Dockerfile -t simpeg-nginx:audit .
```

Manual/browser smoke gate:

- Login works.
- Dashboard loads.
- Cuti create/verify/pdf works.
- Izin create/verify/pdf works.
- Admin user/role/pegawai management works.
- Unauthorized access returns 403/redirect as expected.
- No console errors in critical pages.

Security gate:

- No hardcoded secrets.
- No unresolved Critical/High findings.
- Upload validation tested.
- Authorization tests cover direct UUID access.
- Production env checklist documented.

## Expected deliverables from the full audit

1. `.hermes/audits/production-readiness-YYYY-MM-DD/findings.md`
2. `.hermes/audits/production-readiness-YYYY-MM-DD/verification.log`
3. Code/test fixes for confirmed issues.
4. Final summary:
   - What was checked.
   - What was fixed.
   - Remaining risks.
   - Exact commands run and results.
   - Production go/no-go.

## Open questions before execution

1. Should the audit fix issues directly, or only report findings first?
2. Should generated `public/build` assets be committed in this repo, or built during deployment only?
3. What exact production target will be used: shared hosting, VPS Docker, Laravel Sail, Apache, or Nginx + PHP-FPM containers?
4. Should uploaded personnel/cuti/izin documents be publicly accessible through `storage:link`, or require authenticated download routes?
5. Is qdrant-code expected to be fixed now, or can the first audit run proceed with AtlasMemory + Serena + grep/static analysis?

## Recommended next command

Proceed with Phase 0 and Phase 1 audit on current Simpeg repo, while treating current local changes as part of the audit baseline unless instructed otherwise.
