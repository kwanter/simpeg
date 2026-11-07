# Monthly PKP Dependency Advisory Review

**Date:** 2026-11-06
**Fixed point:** HEAD `1a21593c9357406495403c5b1cb8323da452e625`
**Compared baseline:** `e6e683c` (pre-PKP-implementation commit)
**Spec:** `spec/spec-feature-monthly-pkp.md` v0.4
**Plans:** `plan/plan-monthly-pkp.md`, `plan/plan-refactor-monthly-pkp-v1.0.md`
**Closes:** the `DEP-01` dependency note carried since 2026-10-06
**Verdict:** Dependency risk stays **non-green**. It is not newly introduced by
the PKP work, but one reachable high-severity advisory and an end-of-life
framework remain blocking for release.

## 1. Executive Summary

Fresh audits were run against the committed lockfiles, then every advisory was
traced to source to separate *affected dependency* from *reachable defect*.

- **Composer:** exit 1, 15 advisory records across 7 packages. Those records
  collapse to **14 unique advisories**: the Laravel email-rule finding is
  reported twice (`PKSA-3r5d-mb8f-1qw9` and `PKSA-mdq4-51ck-6kdq`, same
  `GHSA-5vg9-5847-vvmq`).
- **npm:** exit 1, 8 vulnerable package nodes and 24 advisory instances.
  Severity split is 4 high, 4 moderate, 0 critical.
- **Not introduced by this work.** Every affected package is version-identical
  to the pre-PKP baseline, except `postcss-selector-parser` 6.1.2 to 6.1.4,
  which is still inside the vulnerable range. The feature commits added code
  and tests, not dependencies.
- **Reachable and blocking:** the Symfony `Cache-Control` ReDoS is parsed
  during `Request::capture()`, before any middleware, authentication, rate
  limit, or PKP authorization. One request header reaches it.
- **Critical label, no active path:** the Laravel TokenGuard bypass needs a
  configured token API guard. This application defines only the `web` session
  guard, so the critical advisory has no demonstrated route here.
- **Structural blocker:** `laravel/framework` is pinned to `^10.10` and locked
  at 10.50.2. Laravel 10 left security support on 2025-02-04, and every
  Laravel advisory floor in this audit is on the 12.x or 13.x line. No lock
  refresh can clear them.

**Readiness for release: 41/100.**

## 2. Method and Evidence

Read-only. No install, upgrade, migration, build, endpoint probe, or
credential access was performed.

- `composer audit --locked --format=json`: 15 records. Raw output kept at
  `/tmp/simpeg-dependencies-20261106/composer.json`.
- `composer audit --locked --no-dev --format=json`: 13 records, which drops
  `symfony/yaml` as a dev-only dependency.
- `npm audit --json --package-lock-only`: 24 instances. Raw output at
  `/tmp/simpeg-dependencies-20261106/npm.json`.
- `npm audit --omit=dev`: 0 findings. **This number is misleading** and must
  not be quoted as safety evidence; see Section 4.
- Locked versions cross-checked against `vendor/composer/installed.json` and
  each `node_modules/<pkg>/package.json`, so the audit describes what is
  actually installed, not just what is declared.
- Fix feasibility was proven with `composer update --dry-run` and
  `npm install --dry-run`; neither writes.

Two read-only reviewers ran in parallel, one per ecosystem. Their reachability
tables were checked against source before inclusion here.

## 3. PHP Advisories and Dispositions

Locked: `laravel/framework` 10.50.2, `league/flysystem` 3.34.0,
`symfony/http-foundation`, `http-kernel`, `mime`, `routing` all 6.4.41,
`symfony/yaml` 7.4.13 (dev-only, required by Sail).

### Reachable now

- **`GHSA-r925-mgvr-h9cc` / CVE-2026-108082, high: `Cache-Control` ReDoS.**
  Source path is complete: `public/index.php:51-53` calls
  `Request::capture()`, `Illuminate/Http/Request.php:64-68` calls
  `createFromGlobals()`, `symfony/Request.php:277-285` builds the `HeaderBag`,
  and `HeaderBag.php:136-157,284-288` parses the incoming header through
  `HeaderUtils::split()`. This runs before `app/Http/Kernel.php:16-24`, so no
  application control can intervene. Precondition is only that the header
  reaches PHP unnormalized; the padding-run payload is documented upstream.
  Reachability is established. Observed CPU exhaustion on the deployed stack
  is not, and is not claimed.

### Blocked by lifecycle, not by code

- **Laravel advisories (4 unique).** Critical TokenGuard
  (`GHSA-f9x3-7vq9-cph6`), high email CRLF
  (`GHSA-5vg9-5847-vvmq`), moderate temporary signed URL path confusion
  (`GHSA-crmm-hgp2-wgrp`), low debug-page XSS
  (`GHSA-jh5r-qr3c-85q8`).
  - TokenGuard: `config/auth.php:16-43` defines only a `session` guard on the
    `users` provider; no `auth:api`, token driver, or `routes/api.php` entry
    exists. Not reachable through this application.
  - Email CRLF: every mail-writing path validates with
    `App\Rules\SafeEmail` (`app/Rules/SafeEmail.php:11-18`), which rejects
    anything outside printable ASCII, so CR/LF cannot reach the transport from
    those forms. Covered entry points: user create/update
    (`UserController.php:63-78,122-135`), profile
    (`ProfileUpdateRequest.php:17-21`), password reset request and completion
    (`PasswordResetLinkController.php:27-37`,
    `NewPasswordController.php:34-35`). Imported or externally written
    addresses and any future mail sink are not covered by that argument.
  - Signed URL confusion: no local-filesystem `temporaryUrl` call exists.
    Email verification uses `temporarySignedRoute`, which is a different API.
  - Debug XSS: needs `APP_DEBUG=true` in a deployed environment; not
    established either way.
  - **All four stay open in practice because Laravel 10 is out of security
    support.** They are not closable by a patch bump inside `^10.10`.

### Conditional on deployment configuration

- **`GHSA-5mx8-mwrp-5693` (moderate) and `GHSA-3h27-hjv7-fqjx` (low),
  base-URL and script-name handling.** Both need a front-controller or rewrite
  arrangement that this repository's examples do not settle. The production
  Nginx sample (`docker/production/nginx/nginx.conf:20-31,42-51`) does not
  split path info and sets `SCRIPT_NAME=/index.php` only for the upload
  location, while the Apache image uses a `public` document root
  (`docker/apache/000-default.conf`). Live topology needs owner confirmation.
  A trusted-host allowlist does not settle the base-URL case, because the
  attacker controls the base path rather than the host header.
- **`GHSA-wg5q-m2ww-f3c9` (low), inline fragments.** Requires Symfony inline
  fragment rendering. No `FragmentHandler`, `SubRequestHandler`, or ESI
  integration exists in `app/`, `routes/`, or `config/`.
- **`GHSA-jrf8-gvm4-7cgh` (CVE-2026-118514), HEAD versus `methods`.** Applies
  to Symfony SecurityBundle `access_control` and firewall method matchers.
  This project authorizes with Laravel middleware
  (`app/Http/Kernel.php:56-71`, `routes/web.php:92`), not SecurityBundle.
- **`GHSA-xqw3-x93m-887x` (CVE-2026-118516), compiled host placeholders.**
  Laravel does use Symfony's compiled matcher
  (`Illuminate/Routing/CompiledRouteCollection.php:110-122`), but no route
  declares a host placeholder, so the host/path split does not arise. If host
  routes are added later, the route cache must be rebuilt after the upgrade.
- **`GHSA-vqc8-7275-q272` (CVE-2026-45070), MIME parameter names.** Needs
  attacker-controlled parameter *names*, not addresses or values. No
  application `setParameter` or parameterized-header construction was found.

### Not reachable in the traced flows

- **`GHSA-cxf4-7mrp-vvpr` / CVE-2026-102601, low, Flysystem malformed UTF-8
  path normalizer.** PKP stores uploads at a constant directory plus a
  server-generated UUID and fixed `.pdf` extension
  (`PkpDocumentService.php:19-21,85-101`); the request-supplied
  `storage_path` is prohibited (`StorePkpDocumentRequest.php:29-33`), and the
  client filename is metadata only. No PKP-controlled path reaches the
  bypass. Other storage flows and pre-existing rows are outside this trace.
- **`GHSA-hwfg-qwvh-2wfx` and `GHSA-2v3c-qxhr-7vpv`, Symfony YAML.** Locked
  under `packages-dev` for Sail. No YAML parsing of request input exists, and
  no `MapRequestPayload` usage was found.

## 4. npm Advisories and Dispositions

Locked: `axios` 1.18.1, `vite` 8.1.5 (both direct), plus transitive
`follow-redirects` 1.16.0, `glob` 10.5.0, `minimatch` 9.0.9,
`postcss-selector-parser` 6.1.4, `source-map-js` 1.2.1, `sucrase` 3.35.0.

`npm audit --omit=dev` reporting zero is **not** evidence of safety here.
`package.json:8-19` keeps axios in `devDependencies`, yet
`resources/js/app.js:1` imports `bootstrap.js`, which assigns
`window.axios` (`bootstrap.js:7-8`), and the built bundle
`public/build/assets/app-BpHS9st8.js` contains it. Everything Axios ships to
the browser counts as production code regardless of the manifest section.

- **Axios, 12 advisories, not reachable.** The only call site is the PKP draft
  save: `resources/views/pkp/edit.blade.php:288-293` uses `axios.put` with an
  explicit method, a JSON object body, the default XHR adapter, and no
  interceptor or `transformRequest`. Node-only code (`follow-redirects`,
  `fromDataURI`, proxy and HTTP/2 handling, `createConnection`) is stubbed out
  by `node_modules/axios/package.json:50-55` and absent from the bundle. The
  prototype-gadget advisories additionally need an independent
  `Object.prototype` pollution primitive; none was identified in repo client
  code. Not exhaustively disproved across CDN-loaded libraries.
- **follow-redirects, 3 advisories, not reachable.** Imported only by Axios's
  Node adapter, which the browser build nulls; no direct importer in the repo.
- **glob, minimatch, sucrase.** `require('glob')` exists only in
  `node_modules/sucrase/dist/cli.js:3`, and the repo never runs the sucrase
  CLI. Tailwind calls only `transform`. Build-graph noise.
- **postcss-selector-parser and source-map-js.** Build-only, over
  repository-authored CSS and maps; no sourcemap output is configured.
- **Vite, 5 advisories, dev-server only.** The editor-launch variants are
  Windows or WSL specific. The `fs.allow` variant needs the dev server bound
  off-loopback *and* an imported path that mirrors a host absolute path;
  `vite.config.js` exposes only two repo-local inputs. One real exposure:
  `laravel-vite-plugin` forces `host: 0.0.0.0` when `LARAVEL_SAIL` is set
  (`docker-compose.yml:13,16`, publishing 5173), so a Sail-run dev server is
  LAN-reachable with `/__open-in-editor` mounted.

## 5. CI Gates Do Not Enforce the Stated Contract

`CONSTRAINTS.md:14` requires zero new high or critical findings via
`composer audit` and `npm audit`. Neither gate is enforced:

- `.github/workflows/php-tests.yml:90-91` runs
  `composer audit --no-interaction || true`, so the exit status is discarded.
- No workflow runs `npm audit` at all; the three workflows install and build
  only.
- No Dependabot or equivalent automation exists.

Consequence: green CI is fully compatible with these advisories, so any
release decision must not read CI as dependency clearance. This is a
governance defect independent of the vulnerable versions.

## 6. Fix Feasibility Proven Without Applying

Both dry-runs resolved cleanly, which means the patches are available inside
the declared ranges:

- `composer update --dry-run` with
  `symfony/http-foundation:^6.4.50`, `symfony/http-kernel:^6.4.50`,
  `symfony/routing:^6.4.50`, `symfony/mime:^6.4.49`,
  `league/flysystem:^3.35.3` plans 5 upgrades and 0 removals, then still
  reports advisories because Laravel and YAML remain.
- `npm install --dry-run axios@1.20.0 vite@8.1.6 source-map-js@1.2.2
  sucrase@3.35.1` plans 4 changes and removes 41 packages, including the
  `glob` and `minimatch` nodes, because sucrase 3.35.1 replaces `glob` with
  `tinyglobby`.

Applying those two sets clears 8 of 15 Composer records and all npm findings
whose parents allow the patch. What remains is `laravel/framework` (4 unique)
and `symfony/yaml` (2), and only a framework major upgrade closes the first.

## 7. Readiness Scoring

- **Reachability triage: 22/40.** Every advisory has a disposition, but two
  Symfony URL and path findings and the deployment `APP_DEBUG` state depend on
  facts outside the repository.
- **In-range remediation coverage: 15/30.** Symfony, Flysystem, and five npm
  packages are provably patchable now; Laravel's four advisories are not
  patchable on 10.x.
- **Enforcement and monitoring: 4/30.** The audit contract in
  `CONSTRAINTS.md` is not enforced by any gate.
- **Total: 41/100.** No critical-flaw veto; the score already reflects the
  blocking findings.

## 8. Verdict and Handoff

**Release stays blocked** on three items, in this order:

1. Clear the in-range patches (Symfony 6.4.50/6.4.49 set, Flysystem 3.35.3+,
   Axios 1.20.0, Vite 8.1.6, source-map-js 1.2.2, sucrase 3.35.1,
   postcss-selector-parser 6.1.5). This removes the only *reachable* high
   advisory.
2. Make the audit contract real: drop `|| true`, add an npm gate, and decide
   whether the gate evaluates runtime-reachable or all findings.
3. Decide the Laravel 10 end-of-life path. Laravel 12.68.0+ is the nearest
   patched line and keeps a PHP 8.2 floor, so the runtime images do not have
   to move. This is a framework upgrade, not a patch bump, and needs its own
   spec, plan, and approval.

Executable proposal: `plan/plan-refactor-pkp-dependencies-v1.0.md`.
No dependency was modified, installed, or upgraded by this review, and no
deployment, staging, or release authorization is implied.

## 9. Phase 1 Applied (2026-11-06)

The in-range patch set was executed after this review was published. Values
below are measured after the change, not projected.

- **Composer:** 6 upgrades, 0 removals, 0 installs. `symfony/http-foundation`
  v6.4.41 to v6.4.50, `symfony/http-kernel` v6.4.41 to v6.4.50,
  `symfony/routing` v6.4.41 to v6.4.50, `symfony/mime` v6.4.41 to v6.4.49,
  `league/flysystem` 3.34.0 to 3.36.0, `symfony/yaml` v7.4.13 to v7.4.23.
  `composer.json` is unchanged; every upgrade resolves inside the existing
  framework constraints, including the dev-only YAML used by Sail.
- **Composer audit:** 15 advisory records across 7 packages dropped to
  **5 records on a single package**, `laravel/framework`, which collapse to
  four unique advisories. The reachable `Cache-Control` ReDoS is cleared, and
  both YAML parser advisories are gone.
- **npm:** 41 packages removed, 12 changed. `axios` 1.21.0,
  `vite` 8.3.6, `sucrase` 3.35.1, `postcss-selector-parser` 6.1.5,
  `source-map-js` 1.2.2, `follow-redirects` 1.16.2. `glob` and `minimatch`
  are no longer installed at any depth. `package.json` is unchanged.
- **npm audit:** 0 vulnerabilities, down from 8 packages and 24 instances.
- **Regression guard:** `tests/Unit/DependencyFloorTest.php` pins six PHP
  floors, six npm floors, and two forbidden ranges. It failed before the
  change, 3 tests and 27 assertions pass after, and it asserts floors rather
  than exact versions.
- **Fix verified in the vendored code, not just in metadata:**
  `HeaderUtils::split()` now uses possessive quantifiers and only consumes
  whitespace when more of the value follows. The documented worst-case header
  parsed in 0.645 ms with no directive loss, where the advisory measured
  813 ms on PHP 8.5 before the fix.
- **Gates:** full PHP suite 605 passed / 4,605 assertions / 84.21 s; Pint
  PASS on 278 files; `npm run build` green on vite 8.3.6; base Chromium suite
  2 passed; PKP Chromium suite 12 passed with 0 retries.
- **Cache hygiene:** no compiled route cache existed (`bootstrap/cache`
  holds only `packages.php` and `services.php`), and config, route, view, and
  event caches were cleared anyway, which satisfies the upstream requirement
  to rebuild routing state after the matcher fix.
- **Two notes for the record.** Rebuilt CSS drops an unused `.break-all`
  utility because clearing compiled views removed it from Tailwind's scan
  surface; no view references that class, and no at-rule or used selector
  changed. `php artisan optimize:clear` fails on this host only, inside
  `cache:clear`, because phpredis is loaded and `REDIS_HOST` is the Docker
  service name; clearing each cache individually works, and the failure is
  environmental rather than caused by the upgrade.

Still open: the four Laravel advisories, which only the framework upgrade in
Phase 4 closes. Every advisory that any in-range patch could clear is now
cleared. Phase 2 needs explicit approval because it edits CI
configuration.

## 10. Phase 2 Audit Enforcement (2026-11-07)

The user approved CI changes and explicitly selected a four-GHSA Laravel
baseline instead of making CI permanently fail on the known framework debt.
This implements the no-new-high/critical constraint without treating the
existing vulnerabilities as fixed. The earlier review remains historical.

### Composer policy

The PHP workflow runs Composer with `--no-cache`, captures the raw audit JSON,
and preserves the exit status for `.github/check-composer-audit.php`. The
policy in `.github/ComposerAuditPolicy.php` accepts only four reviewed IDs
on `laravel/framework`: `GHSA-f9x3-7vq9-cph6`, `GHSA-jh5r-qr3c-85q8`,
`GHSA-crmm-hgp2-wgrp`, and `GHSA-5vg9-5847-vvmq`.

- Owner: `kwanter`; review deadline: 2026-11-21 UTC, inclusive.
- Reasons: session guard rather than TokenGuard, deployment-dependent debug
  exposure, no traced filesystem temporary URL use, and SafeEmail validation.
- A duplicate advisory from another source is deduplicated by GHSA identity;
  another package cannot reuse an accepted ID to receive an exemption.
- Any unreviewed advisory blocks, regardless of severity. Invalid or filtered
  JSON, abandoned packages, inconsistent process status, missing identities,
  and use of an expired tolerance also block.
- A clean report can pass after the deadline because it uses no exception.
- The raw report and accepted IDs remain visible in the workflow log. Gate
  success is not a clean audit result or release approval.

The original `composer audit --no-interaction || true` was executed before
the change: it reported five records but returned exit 0. This is the
observed failure being corrected. With the new workflow command, the same
raw audit exits 1, while the policy exits 0 only after accepting the four
documented IDs. There is no unconditional success fallback.

### npm policy

The workflow runs the following command after `npm ci`:

```sh
npm audit --include=dev --audit-level=high --package-lock-only --ignore-scripts
```

It includes dev dependencies because Axios ships in the browser bundle and
the build toolchain is part of the attack surface. Moderate findings remain
visible; high/critical findings and audit-service failures block. This
replaces the earlier plan recommendation to omit dev dependencies, which
would have missed shipped Axios. No quality threshold was lowered.

### Local verification

- Real workflow commands were extracted from the edited YAML and executed
  under Bash with `-eo pipefail`: Composer policy exit 0 with exactly the
  four reviewed GHSAs; npm exit 0 with zero vulnerabilities.
- The policy regression suite passes 18 tests / 21 assertions, covering new
  findings mixed with accepted ones, package mismatch, conflicting identities,
  malformed/filtered reports, process failures, and expiry boundaries.
- Seven CLI scenarios produced the expected status: baseline and clean
  reports passed; a new critical finding, invalid JSON, network-error report,
  missing schema, and unexpected process status failed.
- The complete no-cache Composer workflow also failed with exit 1 against
  an unreachable temporary repository; no cached audit was accepted.
- Real npm was run against a disposable local registry fixture. A high
  advisory in a devDependency returned exit 1, moderate returned exit 0, and
  registry HTTP 503 returned exit 1. No dependency was installed or changed.
- Full PHP suite: 623 passed / 4,628 assertions / 87.69 seconds.
- Pint: 279 repository PHP files passed; the two `.github` PHP helpers were
  also checked explicitly and are now included in the workflow lint command.
  Both helpers passed `php -l`.
- Workflow structure passed actionlint with external shellcheck disabled;
  the new shell commands are executed by the smoke checks above.

These local results are separate from the hosted verification below.
Application code, dependency manifests, lockfiles, and production
configuration are unchanged by Phase 2. Laravel remediation and all
deployment/release approvals remain separate.

### Hosted verification (2026-11-07)

Implementation commit `6d46f7e` was pushed to `main`. All results below
belong to the exact SHA `6d46f7e5fa067ed4035d449c7b7cc3bcffd553e6`:

- [PHP Tests][hosted-phase2-php] completed successfully on PHP 8.2, 8.3,
  and 8.4. The inspected PHP 8.2 log records 623 passing tests with
  4,628 assertions, zero npm vulnerabilities, and exactly four accepted
  baseline GHSAs. The Composer gate prints owner `kwanter`, review deadline
  2026-11-21 UTC, and no unreviewed advisories.
- [PKP Database Integration][hosted-phase2-db] completed successfully:
  MySQL, PostgreSQL, migration recovery, race checks, and authenticated
  uploads through the real Nginx/PHP-FPM stack.
- [Playwright Tests][hosted-phase2-browser] completed successfully:
  2 base browser tests, 12 PKP tests, and qpdf verification in both the
  Apache and workspace images.

[hosted-phase2-php]: https://github.com/kwanter/simpeg/actions/runs/42325153933
[hosted-phase2-db]: https://github.com/kwanter/simpeg/actions/runs/42325153844
[hosted-phase2-browser]: https://github.com/kwanter/simpeg/actions/runs/42325153988

TASK-20X is complete. Phase 2 now has both local and hosted evidence;
passing with the approved baseline does not mean the Laravel advisories
are fixed. The baseline expiry and separate deployment/release approvals
remain in force.

## 11. Phase 3 Deployment-Dependent Advisories (2026-11-07)

The three findings that depended on live server topology were probed against
a running Nginx and PHP-FPM pair instead of being decided from the repository
examples.

**Scope:** the pair runs `docker/production/nginx/nginx.conf`, the
configuration this repository ships. These results describe that
configuration, not a confirmed production stack. As of 2026-11-07 no
production deployment exists, so parity is deferred to the first deployment,
where the front controller's `SCRIPT_NAME`, path-information splitting, and
any proxy, CDN, or WAF path rule must be checked before the first release
approval. `plan/plan-refactor-pkp-dependencies-v1.0.md` records the exact
commands. If the live stack differs and exposes a proxy-level problem, the fix
belongs in that Nginx configuration rather than in application code; no such
change has been made.

### Vite dev-server binding

`laravel-vite-plugin` 3.1.3 widens the bind address whenever `LARAVEL_SAIL` is
set and the config declares no host (`dist/index.js:1353-1355`). Resolving the
project configuration through Vite itself returned `0.0.0.0` with
`LARAVEL_SAIL=1` and Vite's default loopback host without it, so a Vite server
started inside Sail was reachable from the LAN together with
`/__open-in-editor`.

- `vite.config.js` now pins `server.host: '127.0.0.1'`.
- `tests/Unit/ViteDevServerHostTest.php` resolves the configuration through
  Vite rather than reading the file text: 3 tests, 9 assertions. It failed
  first with `Failed asserting that an array contains '0.0.0.0'`.
- A real `npx vite` started with `LARAVEL_SAIL=1` listened only on
  `127.0.0.1:5199`, confirmed twice through `lsof`.
- A container process bound to `127.0.0.1` is unreachable through a published
  Docker port (measured: HTTP `000`), so exposing the server inside Sail now
  requires the explicit `npm run dev -- --host 0.0.0.0` override. The guard
  test asserts that override still widens the host, keeping the escape hatch
  deliberate rather than accidental.
- The workspace service in `compose.dev.yaml` publishes `5173` without setting
  `LARAVEL_SAIL`, so Vite there already bound Vite's loopback default and
  already needed that override. This change aligns Sail with the same rule
  instead of tightening only one of the two paths.

### Front-controller topology

The documented production Nginx configuration was run with `php:8.2-fpm` and a
front-controller probe:

- `SCRIPT_NAME` is `/index.php` for every request, and `PATH_INFO` is always
  null: Nginx never splits path information.
- `REQUEST_URI` preserves protocol-relative and backslash forms exactly
  (`//evil.example/x`, `/\evil.example/x`).
- `/index.phpadmin` and `/index.phpstorage/dokumen/secret.pdf` both reach the
  front controller with HTTP 200 while `/storage/dokumen/secret.pdf` returns
  Nginx 404, so the `^~ /storage/dokumen/` rule matches a different path than
  PHP parses.

### Symfony behavior, measured differentially

The same probe ran against `symfony/http-foundation` 6.4.41 (the pre-Phase-1
version) and 6.4.50 (locked):

- `/index.phpadmin`: 6.4.41 derived `baseUrl=/index.php` and
  `pathInfo=/admin`, reproducing the script-name path bypass. 6.4.50 keeps
  `baseUrl=''` and `pathInfo=/index.phpadmin`.
- `/index.phpstorage/dokumen/secret.pdf`: 6.4.41 reduced the path to
  `/storage/dokumen/secret.pdf`; 6.4.50 keeps the full request path.
- `//evil.example/x` and `/\evil.example/x`: both versions returned an empty
  base URL across three `PHP_SELF` variants, so base-URL poisoning was not
  reproduced with these Nginx variables. The patched release additionally
  short-circuits both prefixes in `getBaseUrl()`.
- At the application layer, `root()`, `url('/login')`, and `route('login')`
  stayed on `http://simpeg.test` for all four crafted request URIs.

### Decision

Recorded for the shipped configuration; production parity stays open with the
owner.

- No application code compensates for proxy topology; if a live proxy is later
  found to differ, that Nginx configuration is fixed instead.
- The script-name and base-URL advisories are closed by the Symfony 6.4.50
  floor already pinned in `tests/Unit/DependencyFloorTest.php`. The residual
  Nginx path-rule mismatch only reaches Laravel routes that authorize
  independently, so `^~ /storage/dokumen/` stays as defense in depth and needs
  no patch.
- Host-placeholder routing (CVE-2026-118516) does not apply: no
  `Route::domain` declaration exists. Rebuild the route cache after any future
  Symfony upgrade.
- MIME parameter injection (CVE-2026-45070) and the inline-fragment advisory
  do not apply: no parameterized header construction, `FragmentHandler`, or
  `SubRequestHandler` usage exists.
- Phase 3 changed one configuration file and added one guard test. No
  dependency, lockfile, workflow, or application behavior changed.
