# Release readiness audit

**Audit date:** 2026-10-09
**Scope:** package and independent Laravel 12 demo.
**Decision:** **not ready for a production-oriented first public release**. No algorithm or public contract was changed.

The technical base is sound: exact and fuzzy engines are separate, blocking is explicit, the recommended entry point requires a scope callback, comparison and classification remain distinct, reports are explainable, and an independent demo exercises package discovery. Release preparation is incomplete.

## Findings

| ID | Severity | Concrete evidence | Impact | Recommendation | Blocks first publication |
|---|---|---|---|---|---|
| RR-001 | Resolved (was Critical) | The owner approved MIT; `LICENSE` contains the standard text naming LZK Tools and `composer.json` declares `MIT`. Dependency licenses were reviewed. | The Free package now has explicit use, modification, and redistribution terms. | Preserve the notice in source and archives; approve future Pro terms separately. | No |
| RR-002 | Resolved (was High) | Phase 3C.2B replaces `README.md` with one current path-repository installation, complete declarative configuration, facade usage, scope, canonical/index, classification, privacy, partial-result and limitation guidance. PHP fences and references have executable tests. | New users now receive one internally consistent entry path. | Keep documentation tests and the demo aligned with every API/config change. | No |
| RR-003 | Resolved for 0.1.0 boundary (was High) | `docs/public-api.md` names the recommended facade/manager/configured detector, configuration, DTOs/enums and partial metadata; it labels engines, finders, rules, normalizers, metrics, resolvers and contracts advanced/provisional. | Consumers can distinguish intended 0.x compatibility from accessible implementation code. | Review the proposed boundary before release and keep advanced changes in the changelog. | No for a 0.x preview |
| RR-004 | Resolved (was High) | Phase 3C.2A adds finite exact `max_candidates` (default 1000), remaining-sized keyset pages, examined-candidate counting, and terminal partial metadata. | Exact work is bounded; reaching the ceiling can omit later duplicates. | Keep the limit explicit, blocking selective/indexed, and inspect terminal completeness. | No |
| RR-005 | Partially resolved (High) | PHP 8.2/Laravel 12 and PHP 8.4/Laravel 12-13 pass on SQLite with current and minimum dependency resolutions. PHP 8.3/8.5 were unavailable. PostgreSQL 16 is reachable but lacks isolated credentials; MySQL is absent. | SQLite evidence is substantially broader, while the full declared runtime range and non-SQLite semantics remain unverified. | Add PHP 8.3/8.5 and isolated PostgreSQL/MySQL CI rows before broad compatibility claims. | **Yes for broad compatibility and PostgreSQL/MySQL claims** |
| RR-006 | High | Unknown configuration keys are not consistently rejected. Failures use generic SPL exceptions and iterable failures may occur only during iteration. | Typos may silently disable intent; error handling has no stable package taxonomy. | Before 1.0, define strict keys, error timing, and package exceptions; treat messages as non-contractual. | No for an explicit 0.x preview; **yes for 1.0** |
| RR-007 | Resolved locally; publication verification pending (High) | `SECURITY.md` identifies `lzktools@gmail.com` as the private security reporting contact. Email reception was verified on 2026-10-16. GitHub repository and remote exist. | A private email reporting channel is available; GitHub Private Vulnerability Reporting remains disabled. | Verify the updated policy is published on GitHub and that the contact is monitored. | **Pending verification** |
| RR-008 | Partially resolved (Medium) | Quality and compatibility workflows plus a manual release guide now exist, but no GitHub repository or workflow run exists and the changelog remains Unreleased. | Local definitions improve reproducibility but provide no hosted execution evidence. | Run required workflows after a repository exists and create a dated version section only for the approved release candidate. | No after hosted evidence and release process are established |
| RR-009 | Medium | Complete installation knowledge is split across README, configuration docs, and the demo. Canonical-column lifecycle and indexes are essential for correct use. | README-only onboarding does not reliably reach a safe configuration. | Include one complete configuration and common errors in the README. | Covered by RR-002 |
| RR-010 | Resolved for current lock (Low) | `composer audit --locked` reports no advisories and runtime licenses are MIT, BSD-3-Clause, Apache-2.0, or permissive alternatives. The root package is MIT. | Current dependency metadata is compatible with the selected Free license but changes over time. | Repeat audit and license inventory for every release. | No |

## Recommended API boundary

Support and document for the initial 0.x line:

- `Facades\DuplicateDetector::for(Model::class)`;
- `DuplicateDetectorManager::for(Model::class)` for container use;
- `ConfiguredDetector::scope(...)->detect($source)`;
- service provider, discovery, and `duplicate-detector-config` publish tag;
- documented configuration keys;
- returned DTOs/enums: `DuplicateReport`, `DuplicateMatch`, `ComparisonResult`, `FieldComparison`, `FieldOutcome`, and `RiskClassification`.

Treat resolver/definition classes, concrete engines/finders/comparators/rules/blocking/classifiers/normalizers/metrics and contracts as advanced and provisional during 0.x. They remain usable, but should not receive a 1.0 compatibility promise yet. Do not advertise the current `CandidateFinderInterface` as a common exact/fuzzy extension point.

Configuration and semantics are API: key names/defaults, rule priority, result ordering, missing-value conventions, privacy guarantees, and exception timing can all be breaking changes.

## Result semantics to freeze

Similarity is a metric, not a probability. Classification is a deterministic policy result, not an identity or merge decision. Contradictions remain visible. Missing differs from difference and contradiction. Exact and fuzzy can report the same candidate independently. Built-in reports omit compared raw/normalized values but contain identifiers and metadata that still require authorization. Lazy iterables may query and fail when consumed. Ordering is deterministic only under a stable observed dataset; keyset pagination does not create a snapshot.

## Versioning and release process

Use [Semantic Versioning 2.0.0](https://semver.org/). Start at `0.1.0` because the API and error model are provisional. Even in 0.y, announce incompatible changes prominently. Reach 1.0 only after the supported surface, configuration schema, semantics, errors, and matrix are frozen.

Use changelog sections `Added`, `Changed`, `Deprecated`, `Removed`, `Fixed`, and `Security`. After 1.0, deprecate in a minor and remove in the next major. Tags and release artifacts are immutable.

## Prioritized plan

1. **P0:** configure and verify a monitored private security channel. MIT and the README update are complete.
2. **Resolved for 0.1.0 proposal:** recommended and advanced/provisional API boundaries are documented.
3. **Resolved in 3C.2A:** exact total work is finite and exposes conservative truncation.
4. **P1:** automate the executed PHP/Laravel/current/minimum rows and add PHP 8.3, PHP 8.5, PostgreSQL and MySQL.
5. **P1:** reject unknown configuration keys and define package exceptions before 1.0.
6. **P1:** run the prepared CI in GitHub, retain audit evidence, and install-test the inspected archive.
7. **P2:** execute the performance plan with indexed representative data.
8. **P2:** approve Free/Pro packaging and contribution terms.

## Audit limits

This was a source, documentation, dependency-metadata, and recorded-verification audit. It was not a penetration test, legal opinion, or load test. No benchmark number, new platform result, algorithm change, contract change, database mutation, commit, tag, or publication is claimed.


## Verification executed during this audit

- PHPUnit: **passed**, 151 tests and 474 assertions on PHP 8.4.24.
- Larastan/PHPStan: **passed**, 61 files, no errors.
- PHPCS/PSR-12: **passed**, no output and exit code 0.
- `composer audit --locked`: **passed**, no vulnerability advisories or abandoned packages.
- `composer validate --strict`: **failed with exit code 1** because the root package has no license. The JSON is otherwise valid. This is evidence for RR-001, not a tooling-only false positive.
- Composer emitted deprecation noise from the locally installed Composer PHAR on PHP 8.4; direct project binaries completed normally.
- The Laravel 12 demo was inspected as an external consumer. It was not re-executed in this documentation-only audit; its previously recorded SQLite verification remains the evidence cited in the compatibility matrix.

### Finding-number clarification for Phase 3C.2B

The phase request associates RR-005 with the missing private security channel.
In this audit's canonical table, RR-005 is compatibility coverage and RR-007 is
the security-channel finding. Neither was closed: RR-005 remains pending for the
unexecuted matrix, and RR-007 remains partially resolved until the owner
configures a real monitored private channel.


## Phase 3C.3 status update — 2026-10-09

RR-001 is resolved: the owner approved MIT for the Free package, the standard
license names LZK Tools, Composer declares `MIT`, strict validation passes, and
the current runtime dependency license inventory was reviewed. The future Pro
package remains separate with terms pending.

RR-007 remains partially resolved and blocks publication. No Git repository or
GitHub remote exists, so GitHub Private Vulnerability Reporting cannot be enabled
or verified. `SECURITY.md` now gives repository-admin setup and verification
instructions without claiming that a private channel exists.

Quality and compatibility workflows are prepared but have not run on GitHub.
The corrected local Composer archive passed required and forbidden file checks;
this does not replace a clean release-candidate archive test after Git exists.

## Phase 3C.4 candidate assessment — 2026-10-09

The local `0.1.0` candidate is technically reviewable but remains preliminary.
The archive installs in a fresh Laravel 12 application without a path repository
or symlink, discovery/configuration work, and consumer tests cover the recommended
API including partial results and read-only observation.

**Publication blockers:** RR-007 private reporting is not enabled; no GitHub run,
repository, immutable source commit, tag, final date, or owner publication approval
exists.

**Production-support blockers:** database evidence is SQLite-only; PHP 8.3/8.5
rows have not run; RR-006 configuration/error semantics remain provisional; no
production benchmark or formal operational support window exists.

**Post-release improvements:** engine-selectable PostgreSQL/MySQL integration,
promotion of successful experimental PHP rows, stable error taxonomy, performance
baselines, and advanced API stabilization before 1.0.

The recommendation remains to postpone publication until publication blockers are
closed. Even after publication, production suitability must be evaluated separately
against the production-support blockers and documented package limits.

## Phase 3C.5 security reporting update — 2026-10-16

RR-007 has progressed since the 2026-10-09 assessment.

The GitHub repository now exists at
https://github.com/lzk-tools/lzk-laravel-duplicate-detector
and the initial release-candidate commit (`4aac07c`) has been pushed
to `origin/main`.

The owner designated `lzktools@gmail.com` as the private security
reporting contact and verified that the mailbox receives messages.

`SECURITY.md` has been updated locally to document this channel.
GitHub Private Vulnerability Reporting remains disabled.

The remaining RR-007 verification step is to publish the updated
security policy to GitHub and confirm that the reporting address
is visible and correct.

Other release blockers and compatibility requirements remain subject
to separate verification.

