# SIMPEG Security Audit & Production Readiness

**Date:** 2026-07-22  
**Scope:** Laravel application, auth/RBAC, Cuti/Izin workflows, uploads, dependencies, production Docker/Nginx config  
**Method:** static code review, route/policy matrix, dependency audits, regression tests, production cache/build/Compose validation

## Executive result

**Code-level Fix Wave 1: PASS.** All discovered P0/P1 application flaws were fixed and covered by tests. Independent post-fix review confirmed no remaining P0/P1 in its reviewed security scope.

**Deployment decision: CONDITIONAL GO.** Deploy only after operations complete every item in `PRODUCTION-RUNBOOK.md`, especially HTTPS/proxy config, strong secrets, backup/restore test, and explicit Laravel 10 residual-risk acceptance.

## Fixed findings

| ID | Severity | Finding | Resolution | Verification |
| --- | --- | --- | --- | --- |
| SEC-001 | P0 | Any role with `update cuti`/`delete cuti` could mutate another employee's pending Cuti | Owner scope added; admin/verifikator staff exception explicit | Policy + Feature IDOR tests |
| SEC-002 | P0 | Cuti/Izin documents stored and linked on public disk | New uploads private; policy-gated download routes; Nginx denies legacy raw URLs; migration command moves existing files | Guest/other-owner/owner download tests; command test |
| SEC-003 | P0 | Routes targeted missing `PegawaiController` methods | Dead routes removed; live resource excludes missing `show` | `route:cache`; no generated route names |
| SEC-004 | P1 | Any `update cuti` holder could run all-balance refresh | `updateAllBalances` policy restricted to admin/super-admin | Feature + policy tests |
| SEC-005 | P1 | Guzzle/PSR-7 advisories | Guzzle 7.15.1, PSR-7 2.13.0 | Guzzle advisories gone from `composer audit` |
| SEC-006 | P1 | Cuti omitted verified-email middleware | Cuti routes use `auth,verified`; User implements `MustVerifyEmail` | Verified/unverified Feature tests |
| SEC-007 | P1 | Unsafe production examples/default password | Debug false example, proxy/TLS notes, production seeder refuses missing/default password | Config review |
| SEC-008 | P1 | Proxy/TLS URL handling incomplete | Configurable `TRUSTED_PROXIES`; HTTPS forced in production | Config/route caches pass |
| SEC-009 | P1 | `HOME=/dashboard` but route is `/` | `HOME='/'` | Auth suite |
| SEC-010 | P1 | Upload extension trusted client filename | MIME→extension allowlists; unsupported MIME rejected | Service tests |
| SEC-011 | P1 | Admin could target super-admin User by UUID | Edit/update/delete target isolation | Feature tests |
| SEC-012 | P1 | Approval/balance race could double-deduct | DB transaction + row locks + expected-state validation | Duplicate-approval regression test |
| SEC-013 | P1 | Production Compose used PostgreSQL while provider forced MySQL grammar | Forced grammar removed; Compose explicitly wires pgsql | Compose config validation |
| SEC-014 | P1 | Old frontend tooling advisories | Vite 8.1.5 + Laravel Vite plugin 3.1.3 | Build + both NPM audits clean |
| SEC-015 | P1 | Laravel 10 email CRLF advisory | All attacker-controlled mail addresses use strict ASCII `SafeEmail`; control characters tested; composer ignore removed | SafeEmail tests |
| SEC-016 | P1 | Attachment replacement/migration could delete the only copy after a failed write | New files must persist before DB switch; old files deleted last; migration verifies target before source deletion | Storage failure + migration tests |
| SEC-017 | P1 | Izin update bypassed PERMA type-specific rules | Update now applies same-day/time and max-two-workday rules | Update regression tests |
| SEC-018 | P1 | Cuti index permission shortcut exposed unrelated requests and hid assigned atasan items | Role/owner/assignment-scoped query replaces permission shortcut | Index visibility regression tests |

## Residual risks

### RISK-001 — Laravel 10 upstream advisories

`composer audit` still reports three Laravel advisory records representing two residual issues:

- **High:** GHSA-5vg9-5847-vvmq / CVE-2026-48019 — email CRLF, patched only in Laravel 12.60+/13.10+
- **Medium:** GHSA-crmm-hgp2-wgrp — local filesystem temporary signed URL ambiguity, patched only in Laravel 12.61.1+/13.12+

Containment:

- CR/LF and all non-ASCII/control characters rejected by `App\Rules\SafeEmail` at every attacker-controlled mail boundary.
- App does **not** call filesystem temporary signed URL APIs; private HR downloads use normal authenticated routes and policies.
- Silent Composer advisory ignore removed, so CI/operations cannot miss residual advisories.

**Required owner decision:** accept containment temporarily and schedule Laravel 12.61.1+ upgrade, or block deployment until upgrade. Recommended: upgrade next milestone.

### RISK-002 — CSP compatibility debt

Current UI needs `unsafe-inline`/`unsafe-eval` due Bootstrap/jQuery/CDN legacy patterns. Keep as tracked P2; migrate to nonce/hash CSP separately.

### RISK-003 — Employee photo remains public

Profile photos remain on public disk because UI embeds them directly. Treat photos as internal-public metadata or move behind an authorized image route in next security wave.

### RISK-004 — Operational controls not provable from repository

TLS certificate, firewall, secret manager, Sentry DSN, database backups, restore success, log retention, server patching, and live permissions require deployment-environment verification.

## Verification evidence

| Check | Result |
| --- | --- |
| PHPUnit | **244 passed / 477 assertions** after all security, reviewer, transaction, provisioning, and seeder-permission changes |
| Pint | PASS |
| `npm run build` | PASS — Vite 8.1.5 |
| `npm audit --omit=dev` | 0 vulnerabilities |
| `npm audit` | 0 vulnerabilities |
| Guzzle/PSR-7 audit | Resolved |
| Composer audit | 2 Laravel residual advisories; no silent ignore |
| `php artisan route:cache` | PASS |
| `php artisan config:cache` | PASS |
| Production Compose config | PASS with required `POSTGRES_PASSWORD` supplied |
| `git diff --check` | PASS |

## Production gate

- [x] P0 application findings fixed
- [x] P1 application findings fixed or explicitly contained
- [x] Authz regression tests
- [x] Private Cuti/Izin documents
- [x] Dependency build/audit checks
- [x] Route/config cache checks
- [x] Final full PHPUnit after last transaction change
- [ ] Laravel residual-risk acceptance by owner
- [ ] Production runbook completed by operator
- [ ] Backup restore drill evidence
- [ ] HTTPS/proxy smoke test on deployed host

**Final status:** CONDITIONAL GO — not permission to deploy. Human must complete unchecked gates.
