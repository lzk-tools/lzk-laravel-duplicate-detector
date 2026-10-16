# Free / Pro strategy proposal

This is a product proposal only. The owner selected MIT for the Free package; this document does not select Pro terms, implement Pro features, remove existing behavior, or impose artificial limits.

## Principle

Everything already implemented should remain in the Free package: exact matching, normalized Levenshtein fuzzy matching, built-in normalizers, SQL blocking, processing limits, explicit contradictions, deterministic classification, explainable DTOs, Laravel container/facade/configuration integration, and the safety tests. Security fixes, tenant-scope preservation, read-only behavior, candidate limits, and privacy protections must never become paid-only features.

A future Pro package should add operational scale, workflow, specialized algorithms, and support. It should require the Free package and compose with its supported API, rather than placing license checks or intentionally weak defaults in Free.

## Findings

| ID | Severity | Concrete evidence | Impact | Recommendation | Blocks first publication |
|---|---|---|---|---|---|
| FP-001 | Partially resolved | The Free package is MIT licensed; Pro terms and the commercial model remain undecided. | Premature Pro packaging can create incompatible rights or require removing features users adopted. | Preserve all current Free features and design Pro as a separate additive Composer package after legal/product approval. | **Yes for announcing Pro terms**, not for the MIT Free preview |
| FP-002 | Medium | Current extension contracts are not yet declared stable, and `CandidateFinderInterface` is exact-specific. | A Pro package built immediately against internals would lock both packages to fragile constructors. | Define a narrow stable integration seam before Pro implementation; keep both packages version-constrained and tested together. | No for Free publication |
| FP-003 | Medium | No contribution licensing policy exists. | External contributions can complicate relicensing or dual licensing later. | Choose DCO, CLA, or a no-relicensing-needed permissive model with counsel before accepting material contributions. | No if contributions remain closed initially |
| FP-004 | Low | No validated pricing, demand, or performance data exists. | Feature tiers based on assumptions may not solve buyer needs. | Validate with users and support requests; make no sales forecast in release materials. | No |

## Proposed Free scope

- recommended facade and container API;
- declarative per-model configuration;
- exact and normalized-Levenshtein engines;
- deterministic SQL blocking and documented false negatives;
- normalizers and configurable contradictions;
- deterministic rule classification;
- explainable, value-minimized results;
- batch/keyset processing and hard fuzzy limits;
- SQLite verification and future verified DB adapters;
- all security, compatibility, and correctness fixes;
- public documentation and community issue tracker.

## Potential Pro value

These are candidates, not commitments:

- additional well-defined metrics, token/phonetic comparison, and locale-aware normalization;
- multiple blocking strategies with union/deduplication and recall diagnostics;
- resumable background jobs, checkpoints, queues, retries, and workload scheduling;
- database-specific optimization helpers and large-dataset observability;
- review workflows, audit retention, exports, and role-aware presentation;
- policy packs backed by disclosed validation methodology;
- deployment diagnostics, upgrade tooling, and compatibility reports;
- commercial support, SLA, onboarding, and architecture review;
- an optional dashboard only if product validation justifies it.

Do not sell a “confidence percentage” without a validated mathematical meaning. Do not let Pro authorize merges automatically by default.

## License alternatives

| Alternative | Open source | Commercial/proprietary use | Pro implications | Contribution implications |
|---|---|---|---|---|
| [MIT](https://opensource.org/license/mit) | Yes, permissive | Allowed with notice | Simple open-core Free plus separately licensed Pro; competitors may reuse Free | Low friction; no explicit patent grant |
| [Apache-2.0](https://opensource.org/license/apache-2-0) | Yes, permissive | Allowed with notice/conditions | Similar to MIT with explicit patent terms and NOTICE duties | Patent grant/termination offers clarity; more compliance text |
| [GPL-3.0](https://opensource.org/license/gpl-3-0) | Yes, strong copyleft | Distribution of derivative works carries source/license obligations | Can support dual licensing, but Laravel application integration questions may deter adopters | Relicensing needs ownership/agreements |
| [AGPL-3.0](https://opensource.org/license/agpl-3-0) | Yes, network copyleft | Network use can trigger source-offer duties | Stronger commercial-license incentive, with substantial enterprise friction | Same ownership/relicensing concern |
| Proprietary source-available/EULA | No unless terms meet the Open Source Definition | Controlled by contract | Maximum control, least ecosystem adoption; public Packagist expectations need careful handling | Contributions require explicit terms |
| Dual license | Depends on open option | Paid alternative can grant different rights | Flexible but administratively and legally complex | Copyright provenance/CLA becomes important |

Dependencies currently resolved at runtime are offered under permissive licenses or permissive alternatives, but counsel must review the final dependency graph and chosen option. This document is technical/product analysis, not legal advice. No license should be selected without approval.

## Recommended direction for approval

The owner selected MIT for the Free package. A separate proprietary Pro package remains the proposed open-core structure, but its license and commercial terms require later legal and owner approval.
