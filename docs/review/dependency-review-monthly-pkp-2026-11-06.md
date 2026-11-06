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
