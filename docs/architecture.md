# Architecture

## Implemented boundary: Phase 2A

The provider remains configuration-only. The exact engine is explicitly constructed
and has no container singleton, facade, observer, scheduler or automatic scan.
All existing Phase 1 interfaces remain unchanged.

The implemented path is:

1. ExactRule defines a stable rule ID, normalizers, required matches, SQL blocking
   columns and optional contradiction fields.
2. ExactCandidateFinder reads normalized source blocking values and discovers
   candidates inside a caller-authorized Eloquent query.
3. ExactComparator compares normalized maps with PHP strict equality.
4. ExactDuplicateDetector yields bounded DuplicateReport objects containing
   DuplicateMatch instances.

No fuzzy algorithm, score, risk policy, identity graph, persistence or merge
decision is implemented. RiskClassifierInterface remains an extension contract.

## Rule semantics and validation

Rules are constructor configuration, not a definitive fluent API or a config
resolver. The fields map is an explicit allowlist. All required match fields must
agree. Every blocking field must be a required match field; blocking predicates
are ANDed. This avoids excluding true matches merely because an optional
explanatory field differs, assuming canonical columns are correct.

The engine validates non-empty rule/field/match/block configuration, identifiers,
normalizers, unknown field references, duplicates, conflicting field roles,
source identity and connection, positive batch sizes, missing model attributes
and actual database columns. Table and column names must be simple identifiers.

Built-in string normalizers require UTF-8 string/null inputs. Empty values become
null; absent values never match, including two nulls. Invalid input aborts the
iteration; errors are not silently converted into positive matches.
Applications consuming a generator may already have received earlier batches
before a later malformed row raises an exception.

ExactRule reads raw original model snapshots. Unsaved edits and cast/accessor
transformations do not participate. This prevents the package from invoking
accessors and makes the source semantics explicit. Consumers must load the key
and all configured fields.

## Candidate query safety

The finder clones the Eloquent Builder, applies its enabled scopes once, then clones
the resulting Query Builder. It rejects complex query shapes rather than rewriting
their meaning. It verifies the source class, table, key and connection against
the query model and checks the actual query connection as well.

Schema metadata is read to verify required columns. Existing WHERE predicates and
bindings are nested together before source exclusion, SQL blocks and pagination
conditions are added. This preserves top-level OR logic while preventing it from
bypassing the additional constraints.

The finder uses only bound equality checks on configured canonical columns.
Those columns must already exist and reflect the same normalizer used by the rule.
The package neither creates nor repairs them. A missing source block terminates
without a candidate SELECT. PHP rechecks candidate fields after retrieval, since
SQL collations can admit a broader set of candidates than strict PHP equality.

Only the primary key and compared fields are selected. Results are hydrated into
cloned model prototypes with raw attributes and no relations; retrieved events,
eager loading and accessor/cast evaluation are not used by the engine.

Scopes, model overrides, normalizers and other consumer callbacks remain trusted.
Before-query callbacks are rejected; this is not a sandbox for arbitrary raw SQL,
after-query hooks, database functions or scope code. Read-only database credentials
are an optional additional consumer safeguard, not a package installation requirement.

## Ordering and memory

The cloned query's ordering is replaced with ascending primary-key order.
lazyById performs keyset pagination with a positive batch size. The configured key
must be a unique, non-null single column; int and string keys are preserved without
casting their values. String ordering follows the database collation.

The detector retains at most one report's matches and the candidate fetch batch.
Reports flush when their match count reaches batchSize, independently of candidate
batch boundaries. No matches yields no reports. A candidate batch can contain
zero matching pairs. Exact-multiple fetch counts may require one final empty query.

There is no total-candidate budget, resumability token or snapshot isolation wrapper.
Concurrent inserts/updates by other processes can affect later batches.
Equality blocking is index-friendly but no optimizer plan, selectivity percentage,
runtime or universal recall guarantee is claimed.

## Result semantics

ComparisonResult retains all Phase 1 constructor arguments and appends optional:

- ruleId: the explicit stable rule identifier.
- matchedFields: every configured field with a nonmissing strict match.
- fieldOutcomes: field names mapped to FieldOutcome enum cases.

FieldOutcome distinguishes match, difference, missing and contradiction.
Evidence codes use field.<configured-name>.<outcome>. Only fields explicitly listed
as contradiction fields can produce contradiction codes, and only when both values
are present and different.

A pair is reported if all required match fields agree. Contradictions in other
fields do not suppress it; they remain visible for a consumer policy to evaluate.
No probability, weighted similarity, confidence or risk classification is generated.

DuplicateMatch carries model class and raw keys; it does not retain models or raw
comparison values. DuplicateReport carries a bounded batch. DTOs remain readonly.
Collection types remain PHPDoc contracts rather than a general runtime schema.

## Database portability

The implementation intentionally avoids SQL LOWER/TRIM/regex normalization.
SQLite's built-in LOWER handles ASCII, whereas PHP normalization handles Unicode.
MySQL's string comparison depends on collation, and PostgreSQL supports collations
with different equality/ordering semantics. These are reasons to provide consistent
canonical columns and recheck equality in PHP.

Sources: [SQLite core functions](https://www.sqlite.org/lang_corefunc.html#lower),
[MySQL string comparisons](https://dev.mysql.com/doc/refman/8.4/en/case-sensitivity.html),
[PostgreSQL collations](https://www.postgresql.org/docs/current/collation.html).

SQLite 3.46.1 in-memory integration has been executed. MySQL/PostgreSQL drivers,
DDL, collations, plans and transaction behavior have not been tested.
The package does not alter consumer schema or create migrations.
A consumer index appropriate for tenant + blocking values + key can help lookup,
but schema/index design remains consumer-owned.

## Testing

The original provider tests still boot with no database connections.
Integration tests define transient SQLite fixture tables directly with Schema,
seed fixture rows, then enable PRAGMA query_only before detection.
They inspect emitted SQL, compare original/final fixture contents, guard hydration
events, verify no eager loading and check original Builder/model state.

This verifies the built-in exact implementation on the recorded combinations,
not the safety of arbitrary consumer extensions or quality on production datasets.

## Phase 2A.1 pagination audit

The candidate query is a Query Builder, so `lazyById()` returns database rows and
does not enter Eloquent's hydration path. The finder must select the primary key
under its unqualified alias because Laravel reads that alias from the last row to
build the next page. The selected comparison fields are the complete raw snapshot
used for candidate normalization; blocking-only columns need not be returned.

Bindings are preserved in semantic order. Existing WHERE clauses and their
bindings are nested first, enabled global scopes are already part of that scoped
query, then blocking equality and source exclusion are appended. Later pages append
the key boundary binding. Tests inspect the emitted SQL and bindings, including a
top-level OR, rather than relying only on result rows.

Schema validation is one metadata query per effective finder execution, independent
of the number of pages. It is skipped when a source blocking value is missing.
There is intentionally no shared cache because schema and connection state can
change in long-running applications.

Keyset pagination is deterministic only for a stable dataset under the database's
key comparison order. It is not snapshot isolation. Inserts above the current
boundary may be observed, deletes may disappear, and updates may change later
eligibility. Consumers that need a fixed view must provide an appropriate
database transaction around complete generator consumption.

## Phase 2B fuzzy engine

The fuzzy path is a separate composition rooted at `FuzzyDuplicateDetector`.
It does not reuse or generalize `ExactRule`. The main responsibilities are:

- `SimilarityMetricInterface` defines a local deterministic metric contract;
  `NormalizedLevenshtein` is the first implementation.
- `FuzzyFieldRule` owns one field, normalizer, metric, threshold and its
  required/optional role. `FuzzyRule` validates unique stable identifiers and
  keeps contradiction fields separate from similarity fields.
- `SqlBlockingStrategy` maps raw source fields through normalizers to equality
  predicates on existing database columns. It is mandatory and independent of
  exact matching.
- `FuzzyCandidateFinder` clones the scoped Builder, validates a simple query
  shape and schema, groups original predicates, adds bound blocking/exclusion
  predicates and pages by primary key.
- `FuzzyComparator` creates a `FieldComparison` for every fuzzy field. It skips
  metrics for missing values, records threshold outcomes, and evaluates explicit
  contradiction rules without exposing normalized values.
- `FuzzyLimits` and `FuzzyDuplicateDetector` cap candidates and comparisons
  independently and emit bounded `DuplicateReport` batches.

There is deliberately no aggregate score. A candidate is reported only when all
required fuzzy fields meet their own thresholds. Optional fields explain the
result but cannot compensate for a failed required field. Contradictions remain
evidence and do not change the threshold result into an identity decision.

Candidate rows are selected through Query Builder and copied with raw attributes
into model clones. This avoids Eloquent retrieval events, accessors, casts and
eager loading. The finder accepts only unprojected, single-table scopes without
joins, grouping, limits, locks, unions or callbacks. Consumer tenant predicates,
global scopes and bindings are preserved. The original Builder is not changed.

The keyset iterator has the same consistency boundary as the exact finder: it
does not create a transaction or snapshot. Inserts, updates or deletes between
pages can affect later pages. Candidate and comparison limits make work bounded,
but a fetch page may contain rows beyond the remaining comparison budget; those
rows are not compared.

Normalized Levenshtein operates on Unicode code points, not bytes or grapheme
clusters. Canonically equivalent but differently encoded strings compare as
different unless the configured normalizer makes them identical. Runtime is
quadratic in string lengths; its configurable code-point ceiling is the guard
against unbounded inputs.

## Phase 2C classification layer

`DeterministicRiskClassifier` implements the original
`RiskClassifierInterface`. It depends only on `ComparisonResult`, so exact and
fuzzy engines use the same classification path. Detector algorithms and candidate
queries remain unchanged.

`ClassificationPolicy` contains an ordered list of `ClassificationRule` objects
and an explicit fallback label. Rules are conjunctive: all configured conditions
must match. Evaluation stops at the first matching rule, making overlap resolution
deterministic and visible in configuration.

Supported conditions are comparison-rule IDs, matched fields, per-field minimum
similarities, contradiction mode (`any`, `none`, or `present`), minimum evidence,
required evidence codes, present fields and missing fields. Similarity conditions
read `FieldComparison`; exact-only rules can use matched fields, evidence,
contradictions and outcomes without fuzzy coupling.

The classifier emits `RiskClassification` with policy/rule identity, reasons and
evidence. Reasons describe the satisfied conditions. Evidence is deduplicated in
the order produced by the comparator. The fallback has reason
`classification.default` and no rule ID.

`DuplicateMatchClassifier` is an optional composition service. It creates new
`DuplicateMatch` and `DuplicateReport` DTOs carrying the classification while
preserving comparison objects and record identifiers. This avoids modifying the
existing exact and fuzzy detector APIs.

No rule is supplied by default. Labels have no package-defined severity or
priority. Classification is not a score, probability, identity conclusion or
authorization to mutate records.

## Phase 3A public API and configuration

The recommended API combines a configured service with one narrow fluent step:
`DuplicateDetectorManager::for()` compiles a model definition, and
`ConfiguredDetector::scope()` captures the consumer's authorized Builder
factory. Engine selection and rules remain declarative rather than mutable fluent
state. This keeps security-relevant blocking visible in configuration and avoids
an API that silently invents candidate strategies.

`DetectorConfigResolver` maps the published PHP array to existing exact, fuzzy
and classification objects. It supports a closed alias set for built-in
normalizers and metrics. Resolution validates structure and invariants without
model construction, schema access or SQL. Candidate finders retain runtime schema
validation.

The provider binds the resolver and manager as lazy singletons. The facade points
to the real manager. Direct construction of existing engine objects remains
supported.

`ConfiguredDetector` refuses detection without an explicit scope callback and
validates the returned Builder/model before creating an iterator. It streams
exact reports followed by fuzzy reports and classifies each report through the
existing immutable composition service. Each report is re-yielded with a fresh
sequential key so separate engine iterators cannot overwrite each other when
materialized with `iterator_to_array()`.

No cross-engine deduplication is performed. A record can appear once per engine,
with distinct comparison evidence. The API adds no authorization mechanism:
scope authorization remains the consuming application's responsibility.


## Phase 3C.2A exact candidate ceiling

`ExactCandidateFinder` performs keyset pages sized as
`min(batch_size, remaining_candidates)` and stops as soon as the finite limit is
consumed. No probe page follows the ceiling. Integer, string and UUID key
ordering, scope cloning, global scopes, grouped predicates, bindings, source
exclusion, selected columns and raw hydration are preserved.

`ExactDuplicateDetector` counts every compared candidate, including non-matches.
It retains at most one full match batch so the terminal report can carry
completion metadata without accumulating all results. Terminal exact reports use
`searchComplete`, `candidatesExamined` and `incompleteReason`. Intermediate
reports and unchanged fuzzy reports keep nullable completion metadata. If a
truncated search has no match, an empty terminal report communicates truncation.

A count below the ceiling is complete for the observed iterator. A count equal
to it is deliberately partial because proving exhaustion requires another query.
Keyset consistency under concurrent writes is unchanged.
