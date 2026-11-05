# Project Constitution

> **Project:** SIMPEG (Sistem Informasi Kepegawaian)
> **Mission:** Provide auditable, rule-compliant employee management, leave and permit workflows, two-level approvals, and official document generation.
> **Technology:** Laravel 10, PHP 8.1+, Blade, Alpine.js, Vite, MySQL/SQLite, Spatie Laravel Permission, PHPUnit, Playwright, Docker.
> **Established On:** 2026-09-24

## Core Principles

### I. Test-First Mandate (NON-NEGOTIABLE)

- Functional code is never written before a failing automated test exists at a pre-agreed public seam.
- The Red-Green-Refactor cycle is required for features and bug fixes.
- Tests assert observable state and outcomes rather than private implementation details or incidental interactions.
- Every behavior change includes focused regression coverage and must pass the full relevant test suite.

### II. Specification-Driven Truth

- Product behavior and technical contracts are defined in approved artifacts under `docs/`, `/spec/`, and `/plan/` before implementation.
- Specifications govern behavior; code is the implementation of those specifications.
- When implementation reveals a specification gap or contradiction, update and clarify the upstream artifact before extending behavior.

### III. Vertical Slicing (Tracer Bullets)

- Features are delivered as complete, demonstrable vertical slices across persistence, domain logic, authorization, routes, and user interface as applicable.
- Layer-by-layer horizontal implementation is prohibited when it prevents an independently verifiable user outcome.
- Each slice must include appropriate automated tests at its public seams.

### IV. Domain Language and Workflow Fidelity

- Use canonical SIMPEG terms such as `Pegawai`, `Cuti`, `Izin`, `atasan`, and `pimpinan` consistently in domain-facing artifacts and tests.
- Preserve the two-level approval order: atasan first, then pimpinan, subject to the applicable role and request rules.
- Enforce leave-entitlement rules, authorization, and auditability at the appropriate application boundaries.
- Treat state transitions and concurrent approvals as domain-critical behavior and verify them with regression tests.

### V. Simplicity and Minimal Footprint (Rule 0)

- Choose the simplest implementation that satisfies the approved specification and passing tests.
- Avoid speculative abstractions, duplicate sources of truth, and unrelated refactoring.
- Preserve existing project conventions and minimize the blast radius of each change.
