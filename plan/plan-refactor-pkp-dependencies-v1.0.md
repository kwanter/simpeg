---
goal: Clear reachable dependency risk and enforce the audit contract
version: 1.0
date_created: 2026-11-06
status: In Progress (Phase 3 shipped-config verified; live parity open)
tags: ["refactor", "security", "dependencies", "ci", "tdd"]
---

# Refactoring & Remediation Plan: PKP Dependency Risk

## 1. Traceability: Requirements & Constraints

- **SEC-001:** Remove the reachable high-severity Symfony `Cache-Control`
  ReDoS by moving to patched Symfony 6.4 releases.
- **SEC-002:** Clear the Flysystem normalizer advisory with no PKP behavior
  change.
- **SEC-003:** Clear all npm advisories whose parents permit an in-range
  patch, without widening major ranges or using overrides.
- **GATE-001:** Enforce the dependency contract: reject unreviewed Composer
  advisories and npm high/critical findings across the complete lockfile.
- **DEP-001:** Record and decide the Laravel 10 end-of-life exposure; do not
  silently patch around it.
- **TEST-001:** Every version floor is pinned by an automated check so a
  future lock refresh cannot silently reintroduce the range.
- **PRN-001:** No force upgrades, no `--ignore-platform-reqs`, no dependency
  overrides that break declared parent ranges, no threshold lowering.

Reports: `docs/review/dependency-review-monthly-pkp-2026-11-06.md`.

## 2. Implementation Steps

> **EXECUTION DIRECTIVE FOR AI AGENTS (`/tdd-write-code`):**
> Execute phase by phase through strict TDD. Write the failing check first
> (RED), apply the minimal dependency change (GREEN), verify the full
> applicable gates, then **STOP AND WAIT** for user approval.

### Implementation Phase 1: In-range security patches

- **GOAL-001:** Clear every advisory that a patch inside the declared range
  removes, with the full suite green.

| Task     | Description                | Ref      | Done | Date       |
| -------- | -------------------------- | -------- | :--: | :--------: |
| TASK-101 | RED: dependency floor test | SEC-001  | [x]  | 2026-11-06 |
| TASK-102 | RED: npm floor check       | SEC-003  | [x]  | 2026-11-06 |
| TASK-103 | GREEN: PHP patch set       | SEC-001  | [x]  | 2026-11-06 |
| TASK-104 | GREEN: npm patch set       | SEC-003  | [x]  | 2026-11-06 |
| TASK-10X | VERIFY: full gates         | TEST-001 | [x]  | 2026-11-06 |
| TASK-10Y | APPROVAL: before Phase 2   | PRN-001  | [ ]  |            |

TASK-101 reads `composer.lock` and asserts the patched floors:
`symfony/http-foundation` and `http-kernel` and `routing` at 6.4.50 or newer,
`symfony/mime` at 6.4.49 or newer, `league/flysystem` at 3.35.3 or newer,
`symfony/yaml` at 7.4.23 or newer. It
must fail before the bump and pass after, so the contract survives future
refreshes. Keep it a floor assertion, not an exact version pin, so routine
updates stay legal.

TASK-102 does the same for `package-lock.json`: `axios` at 1.20.0 or newer,
`vite` at 8.1.6 or newer, `source-map-js` at 1.2.2 or newer,
`postcss-selector-parser` at 6.1.5 or newer, and no `glob` node at all (sucrase
3.35.1 removes it). Do not assert exact versions.

TASK-103 executed the proven dry-run set, plus the dev-only YAML patch that
the same dry-run proved in range:

```sh
composer update "symfony/http-foundation:^6.4.50" \
  "symfony/http-kernel:^6.4.50" "symfony/routing:^6.4.50" \
  "symfony/mime:^6.4.49" "league/flysystem:^3.35.3"
composer update symfony/yaml   # 7.4.13 -> 7.4.23, dev-only
```

TASK-104 uses the proven npm set:

```sh
npm install axios@1.20.0 vite@8.1.6 source-map-js@1.2.2 \
  sucrase@3.35.1 postcss-selector-parser@6.1.5
```

Both dry-runs already resolved with 0 removals of application packages, so a
resolution failure during GREEN means the registry moved; stop and report
rather than widening a range.

TASK-10X must show: full PHP suite green, Pint green, `npm run build` green,
PKP Chromium suite green, the new floors green, and both audits re-run with
the remaining findings limited to `laravel/framework` and `symfony/yaml`.
Record the before/after audit counts.

### Implementation Phase 2: Enforce the audit contract

- **GOAL-002:** Make `CONSTRAINTS.md` telling the truth about what CI checks.

| Task     | Description                    | Ref      | Done | Date       |
| -------- | ------------------------------ | -------- | :--: | :--------: |
| TASK-201 | RED: prove gate ignores exit   | GATE-001 | [x]  | 2026-11-07 |
| TASK-202 | GREEN: reviewed Composer gate  | GATE-001 | [x]  | 2026-11-07 |
| TASK-203 | GREEN: full npm audit gate     | GATE-001 | [x]  | 2026-11-07 |
| TASK-20L | VERIFY: local workflow checks  | GATE-001 | [x]  | 2026-11-07 |
| TASK-20X | VERIFY: hosted workflow run    | GATE-001 | [x]  | 2026-11-07 |
| TASK-20Y | APPROVAL: CI sign-off          | PRN-001  | [x]  | 2026-11-07 |

TASK-201 is evidence, not code: run the audit locally, confirm exit 1 while
the workflow step still succeeds, and attach both to the ticket.

TASK-202 edits `.github/workflows/php-tests.yml`. The user authorized Phase 2
and selected a baseline of four reviewed Laravel GHSAs on 2026-11-07, rather
than a zero-tolerance gate that remains red until Phase 4.

`.github/ComposerAuditPolicy.php` permits only these IDs, only for
`laravel/framework`, through 2026-11-21 UTC (owner: `kwanter`):

- `GHSA-f9x3-7vq9-cph6`: no TokenGuard path in the reviewed session guard.
- `GHSA-jh5r-qr3c-85q8`: debug exposure needs deployment review.
- `GHSA-crmm-hgp2-wgrp`: no traced filesystem temporary URL API use.
- `GHSA-5vg9-5847-vvmq`: SafeEmail rejects control characters at entry.

These reasons are scoped triage, not proof of safety or release approval.
Source duplicates of the same GHSA count once. Any other advisory, malformed
or filtered report, inconsistent exit status, or expired tolerance fails.
The workflow runs Composer without a metadata cache and preserves the raw
status for the policy checker; it never substitutes unconditional success.

TASK-203 runs `npm audit --include=dev --audit-level=high` against the
lockfile. Unlike `--omit=dev`, it covers Axios shipped in the browser bundle
and the build toolchain. Moderate findings remain visible but non-blocking;
high/critical findings and audit service errors block. No severity threshold
in `CONSTRAINTS.md` is lowered.

TASK-20L covers local execution of the exact workflow commands, negative
CLI cases, registry-error and high-devDependency probes, the full PHP suite,
and workflow lint. Local verification passed: 18 policy tests / 21
assertions, seven CLI scenarios, three controlled npm registry cases, and
the full PHP suite (623 tests / 4,628 assertions). Repository Pint passed
279 files and both `.github` helpers were checked explicitly.

TASK-20X completed on 2026-11-07 after commit `6d46f7e` was pushed. All three
hosted workflows passed against the same full SHA:
`6d46f7e5fa067ed4035d449c7b7cc3bcffd553e6`.

- [PHP Tests][phase2-php]: PHP 8.2/8.3/8.4, including both audit gates.
- [PKP Database Integration][phase2-db]: MySQL, PostgreSQL, and upload stack.
- [Playwright Tests][phase2-browser]: 2 base tests, 12 PKP tests, and qpdf
  verification in both runtime images.

[phase2-php]: https://github.com/kwanter/simpeg/actions/runs/42325153933
[phase2-db]: https://github.com/kwanter/simpeg/actions/runs/42325153844
[phase2-browser]: https://github.com/kwanter/simpeg/actions/runs/42325153988

Hosted logs confirmed zero npm vulnerabilities and exactly four accepted
Laravel GHSAs, with owner `kwanter` and review deadline 2026-11-21 UTC.
This completes Phase 2 verification, not Laravel remediation or release
approval. Phases 3 and 4 remain separate.

### Implementation Phase 3: Deployment-dependent advisories

- **GOAL-003:** Resolve or consciously accept the three findings that depend
  on live server topology.

| Task      | Description                   | Ref     | Done | Date       |
| --------- | ----------------------------- | ------- | :--: | :--------: |
| TASK-301  | PROBE: shipped Nginx config   | SEC-001 | [x]  | 2026-11-07 |
| TASK-301L | PROBE: live stack parity      | SEC-001 | [ ]  |            |
| TASK-302  | GREEN: pin Vite host          | SEC-003 | [x]  | 2026-11-07 |
| TASK-303  | DOC: topology decision        | SEC-001 | [x]  | 2026-11-07 |
| TASK-30Y  | APPROVAL: before Phase 4      | PRN-001 | [ ]  |            |

TASK-301 ran the topology probe against the Nginx and PHP-FPM pair this
repository ships, not against a live deployment: `SCRIPT_NAME` is always
`/index.php`, `PATH_INFO` is always null, and the `^~ /storage/dokumen/` rule
matches a different path than PHP parses. Comparing
`symfony/http-foundation` 6.4.41 with 6.4.50 reproduced the script-name path
bypass before the Phase 1 floor and showed it closed after. Protocol-relative
and backslash request URIs produced no attacker-chosen base URL in either
version. Details live in
`docs/review/dependency-review-monthly-pkp-2026-11-06.md` section 11.

TASK-301L stays open because these results describe the shipped
configuration only. They become a statement about production once the owner
confirms the live stack matches it: the front controller's `SCRIPT_NAME`,
whether path information is split, and whether any proxy, CDN, or WAF path
rule sits in front of PHP. If the live stack differs and exposes a
proxy-level problem, the fix belongs in `docker/production/nginx/nginx.conf`
(FILE-007) under its own approval, never in application code compensating for
an unknown proxy.

TASK-302 pinned `server.host: '127.0.0.1'` in `vite.config.js`. The guard in
`tests/Unit/ViteDevServerHostTest.php` resolves the host through Vite and
fails when the Sail environment widens it to `0.0.0.0`. A real dev server
bound only `127.0.0.1`, and because a loopback container process is
unreachable through a published Docker port, running Vite inside Sail now
needs the explicit `npm run dev -- --host 0.0.0.0` override that the guard
also asserts.

TASK-303 recorded the decision for the shipped configuration: the Symfony
floor closes the script-name and base-URL advisories, the residual Nginx
path-rule mismatch only reaches Laravel routes that authorize independently,
and no application code compensates for proxy topology. Phase 4 stays blocked
until the user approves an upgrade spec.

### Implementation Phase 4: Laravel end-of-life decision

- **GOAL-004:** Decide the framework path before any release, with its own
  spec and plan.

| Task     | Description            | Ref     | Done | Date |
| -------- | ---------------------- | ------- | :--: | :--: |
| TASK-401 | PROBE: break inventory | DEP-001 | [ ]  |      |
| TASK-402 | DOC: recommend target  | DEP-001 | [ ]  |      |
| TASK-40Y | APPROVAL: upgrade spec | PRN-001 | [ ]  |      |

Four Laravel advisories, one of them critical, cannot be closed inside
`^10.10`: security support ended 2025-02-04 and the patch floors are 12.68.0
and 13.29.0. Laravel 12 keeps a PHP 8.2 floor, so the runtime images do not
have to move. TASK-401 inventories the real breakage surface: `spatie`
packages, `venturecraft/revisionable`, `barryvdh/laravel-dompdf`,
`laravel/ui`, the custom `Kernel`, and the `TrimStrings` override. Do not start
a framework upgrade inside this plan.

## 3. Structural Remedies & Alternatives

- **ALT-001:** Security-only fork or patch files. Rejected: unmaintained
  vendor patches hide the EOL problem and break `composer audit` reasoning.
- **ALT-002:** npm `overrides` for glob and minimatch. Rejected: both majors
  fall outside the declared parent ranges; the sucrase bump removes the nodes
  without breaking the graph.
- **ALT-003:** Blanket `composer audit` allowlist. Rejected: it converts a
  visible risk into a silent one.
- **ALT-004:** Raising `timeout-minutes` in CI. Rejected: unrelated to the
  finding and would mask the ReDoS rather than fix it.

## 4. Files Affected

- **FILE-001:** `composer.json`, `composer.lock` (Symfony and Flysystem
  floors).
- **FILE-002:** `package.json`, `package-lock.json` (five npm floors).
- **FILE-003:** `tests/Unit/DependencyFloorTest.php` and the npm floor check.
- **FILE-004:** `.github/workflows/php-tests.yml` (audit gate).
- **FILE-004A:** `.github/ComposerAuditPolicy.php`,
  `.github/check-composer-audit.php`, and
  `tests/Unit/ComposerAuditPolicyTest.php` (reviewed baseline gate).
- **FILE-005:** `vite.config.js` (dev host).
- **FILE-005A:** `tests/Unit/ViteDevServerHostTest.php` (dev-host guard).
- **FILE-007:** `docker/production/nginx/nginx.conf`. Unchanged in Phase 3;
  this is the only place a proxy-level fix may land if the live stack is
  confirmed to differ from the shipped configuration.
- **FILE-006:** `docs/review/dependency-review-monthly-pkp-2026-11-06.md`
  (topology decision and audit counts).

## 5. Rollback / Recovery Plan

- **RBCK-001:** Phase 1 is lockfile-only. Revert `composer.lock`,
  `composer.json`, `package-lock.json`, `package.json`, run
  `composer install` and `npm ci`, rebuild assets, and re-run the suite. No
  schema or data change exists to undo.
- **RBCK-002:** Phase 2 changes the workflow and adds a policy checker. Revert
  them together only with approval; doing so restores the old audit bypass.
  Never broaden the baseline or extend its expiry merely to pass CI.
- **RBCK-003:** Phase 3 config change is reverted by restoring
  `vite.config.js`. If `SCRIPT_NAME` investigation reveals a proxy problem,
  fix the proxy, not the application.
- **RBCK-004:** If a patched dependency breaks a test, do not lower the floor.
  File the specific incompatibility and hold the release.
