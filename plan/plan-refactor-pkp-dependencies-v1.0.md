---
goal: Clear reachable dependency risk and enforce the audit contract
version: 1.0
date_created: 2026-11-06
status: In Progress (Phase 1 complete)
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
- **GATE-001:** Make `CONSTRAINTS.md` dependency thresholds enforceable:
  `composer audit` must fail the build and `npm audit` must run.
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

| Task     | Description                    | Ref      | Done | Date |
| -------- | ------------------------------ | -------- | :--: | :--: |
| TASK-201 | RED: prove gate ignores exit   | GATE-001 | [ ]  |      |
| TASK-202 | GREEN: composer audit blocking | GATE-001 | [ ]  |      |
| TASK-203 | GREEN: npm audit step          | GATE-001 | [ ]  |      |
| TASK-20X | VERIFY: workflow run           | GATE-001 | [ ]  |      |
| TASK-20Y | APPROVAL: CI sign-off          | PRN-001  | [ ]  |      |

TASK-201 is evidence, not code: run the audit locally, confirm exit 1 while
the workflow step still succeeds, and attach both to the ticket.

TASK-202 edits `.github/workflows/php-tests.yml:90-91`. Modifying CI
configuration requires explicit user approval per
`.agent/protocols/permissions.md`; do not apply it silently.

TASK-203 must pin a policy, because npm has dev-only findings that no release
should block on. Recommended: `npm audit --omit=dev` as the blocking gate for
runtime surface, plus a non-blocking full report, and a comment explaining
that axios ships through the bundle even though it is declared under
`devDependencies`. Do not use `--audit-level=none`.

Do not add a vulnerability allowlist. If a finding must be tolerated, it needs
a named owner, a reason, and a review date in the workflow comment.

### Implementation Phase 3: Deployment-dependent advisories

- **GOAL-003:** Resolve or consciously accept the three findings that depend
  on live server topology.

| Task     | Description              | Ref     | Done | Date |
| -------- | ------------------------ | ------- | :--: | :--: |
| TASK-301 | PROBE: live SCRIPT_NAME  | SEC-001 | [ ]  |      |
| TASK-302 | GREEN: pin Vite host     | SEC-003 | [ ]  |      |
| TASK-303 | DOC: topology decision   | SEC-001 | [ ]  |      |
| TASK-30Y | APPROVAL: before Phase 4 | PRN-001 | [ ]  |      |

TASK-301 answers two questions on the real deployment, not the repo examples:
what `SCRIPT_NAME` and path info the front controller receives, and whether any
path-based rule sits in front of PHP. Those two facts decide the base-URL and
script-name advisories. Do not patch application code to compensate for an
unknown proxy topology.

TASK-302 sets `server.host: '127.0.0.1'` in `vite.config.js`, or documents that
Vite must not run inside Sail. `laravel-vite-plugin` forces `0.0.0.0` when
`LARAVEL_SAIL` is set, which makes `/__open-in-editor` LAN-reachable.

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
- **FILE-005:** `vite.config.js` (dev host).
- **FILE-006:** `docs/review/dependency-review-monthly-pkp-2026-11-06.md`
  (topology decision and audit counts).

## 5. Rollback / Recovery Plan

- **RBCK-001:** Phase 1 is lockfile-only. Revert `composer.lock`,
  `composer.json`, `package-lock.json`, `package.json`, run
  `composer install` and `npm ci`, rebuild assets, and re-run the suite. No
  schema or data change exists to undo.
- **RBCK-002:** Phase 2 is a workflow edit. Revert the file; CI behavior
  returns to the current non-blocking state.
- **RBCK-003:** Phase 3 config change is reverted by restoring
  `vite.config.js`. If `SCRIPT_NAME` investigation reveals a proxy problem,
  fix the proxy, not the application.
- **RBCK-004:** If a patched dependency breaks a test, do not lower the floor.
  File the specific incompatibility and hold the release.
