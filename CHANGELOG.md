Changelog
[0.1.0] - Unreleased
This is the first release candidate, pending publication approval. It is not yet
published or declared production-ready.
Added
- Read-only exact duplicate detection with deterministic normalization, explicit blocking, stable ordering, bounded candidate examination, and partial-result metadata.
- Independent fuzzy detection using normalized Levenshtein similarity, configurable thresholds, mandatory SQL blocking, bounded work, and explicit contradiction rules.
- Deterministic explainable classification over exact and fuzzy reports without probabilities, merge decisions, or global weighted scores.
- Laravel container manager, facade, mandatory scoped public API, declarative configuration, package discovery, and publishable configuration.
- Explainable DTOs that omit compared raw and normalized personal values in built-in engines.
- MIT license for the Free package, release/security documentation, local archive controls, and prepared GitHub Actions workflows.
Fixed
- Preserve exact candidate ceilings independently from batch size and expose conservative terminal completeness metadata.
- Preserve combined exact/fuzzy reports when iterators are materialized.
- Exclude vendor/, lock files, tests, tools, caches, CI metadata, and development configuration from the Composer archive.
Known limitations
- Scope callbacks are supplied by consumers and cannot prove authorization or tenant isolation.
- Blocking and finite candidate limits can produce false negatives; keyset pagination is not a database snapshot.
- SQLite is the only verified database engine. PostgreSQL and MySQL are not verified.
- GitHub Actions compatibility matrix passed on PHP 8.2, 8.3, 8.4, and 8.5 with the configured Laravel 12/13 combinations (commit 2f2352c).
- Security vulnerabilities can be reported privately to lzktools@gmail.com; the published security policy has been verified on GitHub.
- Configuration error taxonomy and unknown-key handling remain provisional for the 0.x line.
- The package does not merge, update, delete, infer identity, or authorize automated decisions.
Development history — Distribution preparation
Added
- MIT license for the Free package, owned by LZK Tools.
- GitHub Actions definitions for quality gates and the verified/experimental compatibility matrix.
- Explicit Composer archive exclusions and a manual release guide.
- A verified private email vulnerability-reporting channel documented in SECURITY.md; GitHub Private Vulnerability Reporting is not enabled.
Changed
- GitHub Actions Quality and Compatibility workflows passed for commit 2f2352c across the configured compatibility matrix.
No release tag, Packagist publication, Pro implementation, algorithm, or public contract change is included. The GitHub repository and remote are configured.
Development history — Phase 3A
Added
- Validated declarative configuration for admitted models, exact/fuzzy engines, blocking, limits and policies.
- Container-backed manager, mandatory scoped request API and facade.
- Installation and complete configuration guides.
- Integration tests for container resolution, configuration safety, scopes, limits, privacy and read-only behavior.
Fixed
- Re-yield combined engine reports with fresh keys so materialization cannot overwrite earlier batches.
Existing direct engine APIs remain available and unchanged.
Development history — Phase 2C
Added
- Ordered deterministic classification policies over exact and fuzzy comparison results.
- Conditions for matched fields, per-field similarity, contradictions, evidence and missing data.
- Explainable policy/rule identities, reason codes and safe evidence in classifications.
- Optional immutable match/report classification composition service.
Changed
- Extend RiskClassification with backwards-compatible optional policy, rule and evidence metadata.
- Document explicit fallback behavior, first-match priority and classification limitations.
No weighted/global score, machine learning, automatic merge, dashboard, migration or write path is included.
Development history — Phase 2B
Added
- Independent fuzzy engine with per-field normalized Levenshtein metrics and thresholds.
- Mandatory SQL equality blocking, candidate/comparison limits and incremental reports.
- Explicit contradiction rules and privacy-minimal per-field comparison evidence.
- Unicode, scope, tenant, batching, read-only and exact-regression tests.
Changed
- Extend ComparisonResult with optional per-field comparisons while preserving existing construction.
- Document fuzzy metric semantics, performance, blocking false negatives and provisional usage.
Development history — Phase 2A
Added
- Deterministic normalizers, exact rules, scoped candidate discovery and bounded exact reports.
- Rule IDs and explicit match/difference/missing/contradiction outcomes.
- SQLite integration tests with query-only enforcement and SQL observation.
Phase 1 foundation (unreleased history)
- Composer package foundation, service provider, contracts, DTOs and test infrastructure.
- Architecture, decision, verification and roadmap documentation.
No release is included.