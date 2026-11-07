# ADR 0002: Upgrade Target for the End-of-Life Framework Line

- **Status:** Proposed
- **Date:** 2026-11-07
- **Deciders:** repository owner
- **Ref:** `spec/spec-refactor-laravel-12-upgrade.md`

## Context

`laravel/framework` is locked at 10.50.2. Upstream security support for the
10.x line ended on 2025-02-04, so every fix below the current advisory floors
has to come from a framework upgrade rather than a patch. `composer audit`
reports four distinct advisories against the locked version, one of them
critical.

The application sits on PHP 8.2 in `docker/apache/Dockerfile` and in the first
entry of the CI matrix. Its dev tooling is pinned to the Laravel 10 generation:
collision 7, PHPUnit 10, ignition 2.0. Exact decimal arithmetic for the PKP
module depends on `brick/math`, and document generation depends on
`barryvdh/laravel-dompdf`.

## Decision

Upgrade to `laravel/framework ^12.69`, keeping PHP 8.2 and the existing
application structure.

Companion moves in the same change: collision `^8.9`, PHPUnit `^11.0`,
ignition `^2.12`, `spatie/laravel-permission` resolved on its existing `^6.9`
line. `brick/math` and `laravel/tinker` stay where they are.

## Alternatives considered

- **Laravel 11.** Rejected as unimplementable, not merely undesirable. The
  CRLF advisory covers `illuminate/mail` 11.x, and Composer's advisory policy
  refuses to install that line.
- **Laravel 13.40 on PHP 8.3.** Rejected for now. It additionally requires
  `laravel/tinker ^3.0` and `brick/math ^0.14.2 || ^1.0`, and it forces the PHP
  floor up in both the Docker image and the CI matrix, for no advisory coverage
  that Laravel 12 does not already provide.
- **Staying on 10.x with the four advisories accepted.** Rejected. The critical
  advisory is a TokenGuard authentication bypass; it is not reachable in this
  configuration today, but accepting a critical framework advisory indefinitely
  makes every future audit finding indistinguishable from the known set.
- **Forking or hand-patching the framework.** Rejected as unmaintainable.

## Consequences

Positive: the framework returns to a supported line and all four advisories
close; the PHP floor and runtime images stay put; the reviewed four-GHSA
baseline in the audit gate becomes inert rather than load-bearing.

Negative: Carbon moves to 3.x, whose `diffIn*` methods are signed where 2.x
returned absolute values. The Cuti Besar tenure check depends on that sign and
would silently reject every employee, so it needs a fix and a characterization
test. PHPUnit 11 also deprecates the `@dataProvider` doc-comment in favour of
attributes, which touches nineteen call sites in `tests/`.

Reversal cost is high but bounded: reverting the framework constraint plus the
lockfile and restoring the application seam, with the four advisories open
again.
