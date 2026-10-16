# Public API and stability policy

**Target:** first preview release `0.1.0`
**Status:** proposed boundary for the `0.x` series; release review is not complete.

This document defines which symbols and semantics LZK Tools intends consumers to
use. PSR-4 makes other classes technically accessible, but accessibility alone
does not grant a stability guarantee.

## Recommended API

The following surface is recommended for `0.1.0`.

### Laravel entry points

- `Lzk\DuplicateDetector\Facades\DuplicateDetector::for(class-string<Model>)`
- `Lzk\DuplicateDetector\DuplicateDetectorManager::for(class-string<Model>)`
- `Lzk\DuplicateDetector\ConfiguredDetector::scope(callable)`
- `Lzk\DuplicateDetector\ConfiguredDetector::detect(Model)`
- package discovery through `DuplicateDetectorServiceProvider`
- configuration publication tag `duplicate-detector-config`

`scope()` returns a configured clone. `detect()` returns a lazy
`iterable<DuplicateReport>`; queries and runtime schema validation occur when it
is consumed. Exact reports precede fuzzy reports. Cross-engine matches are not
deduplicated.

### Declarative configuration

The documented schema below `duplicate-detector.models` is public configuration:

- model class keys;
- `exact` fields, matching, blocking, contradiction fields, `batch_size` and
  `max_candidates`;
- `fuzzy` fields, normalizers, metric aliases, thresholds, blocking, and limits;
- deterministic `classification` policy and ordered rules.

Supported built-in aliases are `email`, `text`, `phone`, and
`normalized_levenshtein`. Configuration key names, value types, defaults, rule
ordering, and failure behavior are compatibility-sensitive.

The legacy root `batch_size` key remains published for compatibility but is not
used by configured model engines. Consumers should use `exact.batch_size` and
`fuzzy.limits`.

### Returned DTOs and enum

These readonly value objects are part of the recommended result surface:

- `DTO\DuplicateReport`
- `DTO\DuplicateMatch`
- `DTO\ComparisonResult`
- `DTO\FieldComparison`
- `DTO\FieldOutcome`
- `DTO\RiskClassification`

Consumers may read their public properties and enum values. Constructor use is
supported for application-side result handling, but new optional constructor
parameters may be added during `0.x`. Array element shapes documented in PHPDoc
are part of the intended contract.

Similarity values are metric outputs, never probabilities. Classification is a
policy label, never authorization to merge. Missing, difference, match, and
contradiction are distinct outcomes.

## Partial exact-result contract

Only a terminal exact report publishes non-null completion metadata:

| Property | Meaning |
| --- | --- |
| `searchComplete === true` | Exact iteration ended before the configured candidate ceiling. |
| `searchComplete === false` | Exact iteration reached the ceiling and may have omitted candidates. |
| `searchComplete === null` | Intermediate exact report or an engine without this metadata. |
| `candidatesExamined` | Number of exact candidates compared, including non-matches. |
| `incompleteReason` | Currently `max_candidates_reached` when incomplete. |

A terminal report can contain zero matches. Consumers must inspect metadata
before discarding empty reports. Reaching exactly the limit remains incomplete
because no additional existence query is issued.

This metadata describes only exact discovery. It does not prove global pipeline
completeness, fuzzy completeness, blocking recall, snapshot consistency, tenant
authorization, or uniqueness.

## Advanced provisional API

The following remain intentionally advanced and provisional during `0.x`:

- concrete classes in `Exact\`, `Fuzzy\`, `Classification\`,
  `Normalizers\`, and `Metrics\`;
- `Configuration\DetectorConfigResolver` and
  `Configuration\ConfiguredModelDefinition`;
- candidate finders, comparators, rules, blocking objects, policies, and concrete
  classifiers;
- every interface in `Contracts\`.

These classes are tested and can be used for advanced integrations, but
constructor signatures, extension roles, and namespace organization can change
in a `0.x` minor release with changelog notice. In particular,
`CandidateFinderInterface` describes the existing exact finder shape and is not
a common exact/fuzzy extension contract.

No visibility or namespace is changed merely to simulate an internal boundary.

## Provisional SemVer policy

The project follows [Semantic Versioning](https://semver.org/) with additional
`0.x` disclosure:

- `0.1.z` patches contain compatible bug/security fixes and documentation
  corrections where practicable.
- A `0.y.0` minor may contain an incompatible change while the API is
  provisional. Every such change must be called out under `Changed` or
  `Removed` in the changelog with a migration note.
- Breaking changes to the recommended API, configuration keys/defaults, result
  semantics, enum values, ordering, privacy guarantees, or exception timing are
  treated as incompatible even when PHP signatures are unchanged.
- Advanced provisional APIs can change in `0.x` minors, but changes still require
  changelog notice.
- Security fixes are released on the maintained `0.x` line once a release and
  support policy exist. A security fix can require an incompatible change when
  preserving behavior would leave users exposed; the release notes must explain
  that decision without disclosing exploit details prematurely.
- No `1.0` stability claim will be made until release blockers, error taxonomy,
  configuration validation, and the compatibility matrix are reviewed.

Release tags and artifacts, once publication is approved, are immutable.

## Backward-compatibility review checklist

Before changing the recommended surface, review:

1. PHP signatures and named arguments.
2. configuration keys, defaults, accepted types, and validation timing;
3. DTO properties, array shapes, enum cases, JSON shape, and empty-report
   behavior;
4. exact/fuzzy report order and lazy execution;
5. partial-result semantics and reason codes;
6. error classes and query timing;
7. privacy and read-only guarantees.

The Free package uses the MIT License. The first release remains pending owner approval.
