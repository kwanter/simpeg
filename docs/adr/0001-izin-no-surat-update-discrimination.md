# 0001 - Discriminate the Izin No-Surat Update by payload shape

**Date:** 2026-09-24
**Status:** Proposed

## Context

`UpdateIzinRequest` serves two different operations through one route, `PUT izin/{izin}`: a No-Surat Update that records only the Nomor Surat Izin of an already atasan-approved Izin, and a Full Update that rewrites the substantive attributes of an unverified Izin. The current implementation infers the caller's intent from the payload shape: a request counts as a No-Surat Update when the Izin is atasan-approved, a `no_surat_izin` key is present, and the payload holds at most three entries.

That threshold of three is not arbitrary — it is exactly the size of the real edit-form submission (`_token` + `_method` + `no_surat_izin`) — but the reasoning existed only in a source comment. The coupling is invisible to the contract, and a behaviour-preserving refactor of `IzinController` is underway that depends on a 320-test characterization suite pinning the current HTTP behaviour.

## Decision

We keep the payload-shape heuristic and make it explicit, instead of replacing it with a dedicated route or an explicit marker field. The contract is documented on `UpdateIzinRequest::isNoSuratUpdate()`, evaluated at most once per request, and pinned by tests at both ends of the three-entry boundary.

## Consequences

The public update contract is unchanged, so the characterization suite stays valid and the refactor remains independently reviewable. The cost is that the discriminator stays coupled to the edit form: adding any field to the no-surat submission silently moves that request into the Full Update path. That coupling is now documented and test-pinned rather than latent, but it is not removed.

## Considered Options

- **Dedicated route (`PUT izin/{izin}/no-surat`).** Rejected: it changes the public contract and invalidates the characterization tests for the no-surat path, which are the safety net for the current refactor.
- **Explicit marker field (for example `update_mode=no_surat`).** Rejected: it requires a client payload change and a matching view change, so every existing no-surat submission would break until the form was updated.
