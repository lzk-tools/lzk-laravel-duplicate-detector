# Technical decision record

Status: Phase 1, provisional public contracts. Date: 2026-10-07.

## ADR-001: Runtime support

Declare PHP ^8.2 and Illuminate Database/Support ^12.0 || ^13.0.
Laravel 12 retains security support; Laravel 13 requires PHP 8.3.
Laravel 11 is excluded because its support has ended.
The installed dependency solver and actual checks, not a version table alone,
determine verification claims. PHP 8.2/8.3/8.5 execution remains pending.

Source: [Laravel support policy](https://laravel.com/docs/13.x/releases#support-policy).

## ADR-002: Minimal dependencies

Require only the Illuminate components referenced by public code.
The full framework is a development dependency brought by Testbench.
Use PHPUnit ^11.5 for a common PHP 8.2-compatible runner,
Testbench ^10.0 || ^11.0, Larastan ^3.0, PHPStan ^2.0 and
PHP_CodeSniffer ^3.13 for executable PSR-12 validation.
No AI SDK or service client is added by the package.
Development transitive dependencies are recorded by Composer locally.

Sources: [Testbench 10 manifest](https://github.com/orchestral/testbench/blob/10.x/composer.json),
[Testbench 11 manifest](https://github.com/orchestral/testbench/blob/11.x/composer.json),
[Larastan manifest](https://github.com/larastan/larastan/blob/3.x/composer.json).

## ADR-003: Postpone facade and empty directories

Do not create Facades/DuplicateDetector.php until a real detector service exists.
A placeholder facade would imply an operational API or an unresolvable binding.
Do not add empty Exceptions or Support directories. Use PHP's
InvalidArgumentException for invalid DTO metadata.

## ADR-004: Add two small result DTOs

ComparisonResult and RiskClassification supplement DuplicateMatch and
DuplicateReport. They make evidence/contradictions and policy classification
explicit without a loosely typed shared result array.
No contradiction analyzer contract is added yet: the comparison result already
has a place for contradictions, while strategy composition is undecided.

## ADR-005: No implied confidence or merge authorization

Store optional metric values separately from policy classifications.
Require a metric name alongside each score and reject non-finite values.
Define no confidence percentage, score formula, automatic threshold or merge API.

## ADR-006: Batches without an orchestration API

Candidate discovery returns iterable models and accepts a positive batch size.
Reports represent bounded batches. No fluent builder, queue system, persistence,
graph layer, resume token or full-scan service is introduced in this phase.
These obligations are documented, not implemented by the interfaces.

## ADR-007: Independent foundation

The starting directory was empty. The five conceptual reference documents were
not available in the workspace. No external application code, schema or
domain-specific rule was used. No Git repository, commit, remote or publication
was created.

## ADR-008: License and releases remain undecided

Omit the license field and LICENSE file instead of inventing an open-source or
proprietary license decision. Composer's missing-license warning is expected.
No package version is hard-coded; Composer may infer 1.0.0 in a directory without
VCS metadata, which is not a project release.

## ADR-009: Development resolution

Ignore vendor, local tools, caches and composer.lock for the library.
Keep local lockfiles for reproducibility of the verification session, without
constraining consumer dependency resolution. A future CI matrix should resolve
each supported combination and archive its versions. No remote CI run is claimed.

## Decisions pending

- License, contribution terms, distribution channel, pricing and Free/Pro boundary.
- First supported release, public API stability/versioning and release process.
- Model/field configuration schema and normalization option validation.
- Candidate blocking, database support, tenant rules, ordering and comparison budgets.
- Mathematically defined metrics and evidence vocabulary.
- Contradiction policies, risk meaning, thresholds and calibration datasets.
- Composite keys, connection identity and cross-model comparisons.
- Streaming/report completeness, cancellation and resumability.
- CI execution on remaining PHP versions and minimum dependency combinations.
- Review of reference documents if supplied, without importing application code.


## Phase 2A addendum — 2026-10-07

The entries above record Phase 1 decisions and are retained as history.
The user explicitly authorized Phase 2A. The following decisions extend that
foundation; they do not authorize subsequent phases.

### ADR-010: Keep interfaces; inject an explicit rule

CandidateFinderInterface and ComparatorInterface are unchanged.
ExactRule is injected into concrete implementations and configures normalizers,
required match fields, canonical blocking columns and contradiction fields.
ExactDuplicateDetector is a small, explicitly constructed orchestration service.
There is no facade, fluent API, container resolution convention or automatic
interpretation of the reserved models config. This revises ADR-006 only to add
the authorized bounded exact flow; the public API remains provisional.

### ADR-011: Canonical SQL blocking is a precondition

Require at least one equality block mapping a required match field to an existing
canonical column. Combine all blocks with AND and bind all values.
Columns must already be populated consistently by the consumer's data model;
the package neither introduces schema nor backfills data. A raw compared column
can be used only when its stored content is already canonical.

This deliberately avoids SQL normalization because database Unicode/collation
behavior differs, and wrapping columns in functions can hinder ordinary indexes.
The consumer is responsible for canonical column correctness and indexes.
Missing source values produce no candidate SELECT, never a fallback global search.
Incorrect canonical values can omit true duplicates; this is a documented recall limit.

### ADR-012: Clone, scope and group before keyset paging

Preserve authorization/tenant predicates and the caller's enabled/removed scopes.
Nest the original WHERE predicates before adding blocking, source exclusion and
pagination, including when they contain top-level ORs.
Only support a single unprojected table; fail on query shapes whose semantics
would be changed by paging/projection. Replace ordering on the clone with ascending
primary-key order. Do not add pagination offsets or materialize the full table.

Read only key + compared fields, hydrate without retrieved events/eager loads, and
read raw original model snapshots without accessors/casts. Dirty source attributes
are intentionally ignored. The source and query must agree on model and connection.
Custom consumer hooks remain trusted and are not sandboxed.

### ADR-013: Deterministic string normalization

Add EmailNormalizer, TextNormalizer and PhoneNormalizer with a small shared
AbstractStringNormalizer for UTF-8 validation, trimming and missing-value handling.
Require mbstring explicitly. Strings/null only; reject unknown options and lossy
scalar coercions. Null/empty means missing; phone separator-only input is also missing.
No country assumptions, provider-specific email rules, NFC/NFKC or extensions parser.

### ADR-014: Explicit outcomes and compatible DTO extension

Append ruleId, matchedFields and fieldOutcomes optional arguments to ComparisonResult.
Existing Phase 1 positional and named construction remains valid.
Add FieldOutcome with match, difference, missing and contradiction cases.
No raw compared values are emitted. Rule IDs are consumer-owned stable versioned
identifiers; change them when rule semantics change.

Exact matches require all matchFields to agree. Differences become contradictions
only for explicitly configured contradictionFields. These fields are separate
from required match fields; contradictions remain visible on reported matches.
No risk policy, score, probability or merge authorization is implemented.

### ADR-015: Database-backed tests without migrations

Create and seed only transient SQLite fixture tables in tests, then switch the
connection to PRAGMA query_only before detection. Inspect SQL and fixture contents.
Keep original provider tests database-independent.
Test query immutability, global scopes/soft deletes/tenant filters, top-level OR,
multiple batches, stable order, custom string keys, privacy and invalid config.

The environment lacked PDO SQLite. A matching official Debian PHP 8.4.24 package
was downloaded and extracted under the project's ignored .tools directory and
loaded using PHP_INI_SCAN_DIR for test processes only. No global installation,
consumer schema modification or migration was performed.

### ADR-016: Provisional API and analysis boundaries

No additional Composer package dependency was needed. mbstring is the only new
explicit runtime extension. PHPStan remains at level 8; public PHPDoc inputs are
not assumed certain so defensive runtime checks are analyzed rather than removed.
No baseline or ignored-error entries were added.
The exact finder validates inputs when its lazy iterator is consumed.

### Current pending decisions

Phase 2A settles the initial exact-rule object, normalization behavior, basic
contradiction semantics and bounded reports. Still pending:

- License, pricing, Free/Pro boundary, release process and stable API commitment.
- A declarative model configuration schema and a validated config-to-rule resolver.
- MySQL/PostgreSQL execution, additional PHP runtimes and lowest dependency tests.
- Canonical-data lifecycle guidance and production index/performance benchmarks.
- Total candidate budgets, concurrent-change guarantees, cancellation and resuming.
- Composite keys, schema-qualified tables and cross-model/connection matching.
- Later risk policies, additional metrics and production evaluation datasets.
- Any work beyond Phase 2A, which requires approval.

## Phase 2A.1 audit addendum — 2026-10-07

### ADR-017: Keep Query Builder keyset pagination and manual raw hydration

ExactCandidateFinder deliberately invokes `lazyById()` on the cloned
`Illuminate\Database\Query\Builder`, after selecting the qualified primary key
and configured comparison fields. It therefore receives `stdClass` rows rather
than Eloquent models. The finder copies those rows into a clone of the query model
with `setRawAttributes()`, clears relations and marks the clone as persisted.
This avoids `newFromBuilder()`, `retrieved` events, accessors, casts and eager loads.

Laravel 12.69.3 and 13.35.0 both clone the query for each page, use
`forPageAfterId()`, execute `get()`, yield the fetched rows and derive the next
page boundary from the selected key alias. Laravel 13 additionally supports
pre-existing limits/offsets and uses `getLastIdFromChunk()`; the finder rejects
limits and offsets, so that branch is intentionally outside the supported query
shape. No contract or public API change is needed.

Integer keys retain integer values from PDO. String and UUID keys retain strings.
Paging for strings/UUIDs follows database comparison and collation order; it does
not assume UUID chronology. The key must remain unique, non-null and immutable
during a run. Composite keys remain unsupported.

### ADR-018: Validate schema once per effective finder execution, without cache

`getColumnListing()` is a metadata query. It now runs once per finder iteration
that has every required blocking value, before candidate pages. Missing blocking
values return before schema inspection and candidate SQL. The validation remains
because it gives a stable package exception for missing configured columns.

No process-global or static schema cache is introduced. Such a cache would need
correct invalidation for migrations, long-running workers, table prefixes,
connection changes and dynamically provisioned tenants, and could grow without
bound. A per-call cache adds no value because the listing is already fetched once
and reused for every page. Consumers that repeatedly execute detection should
include this metadata query in their cost model.

### ADR-019: Keyset pagination provides no implicit snapshot

The finder does not start a transaction or choose an isolation level. A row with
a key greater than the last emitted key that is inserted between pages can appear
later in the same run. Rows deleted before their page can disappear; updates to
blocking, scope or compared fields can include, exclude or change later results.
Rows inserted at or below the boundary are not revisited. This behavior was
reproduced on SQLite by inserting a higher integer key after the first page.

A consumer requiring a stable snapshot must choose a database-specific transaction
and isolation policy around consumption of the whole generator. The package does
not impose one because transaction duration, MVCC behavior and operational costs
differ across SQLite, PostgreSQL and MySQL.

### ADR-020: Database audit boundary

SQLite 3.46.1 was executed in memory. PostgreSQL and MySQL PDO extensions are
installed, but no clients or database servers were available, so no integration
claim is made for those engines. The generated finder SQL uses Laravel grammar,
bound equality, qualified simple identifiers and keyset predicates, but collation,
metadata cost, query plans, isolation and UUID ordering still require real-engine
test matrices before a compatibility claim.

ExactRule remains specific to exact matches. No fuzzy strategy, scoring,
classification or generic rule abstraction was added during this audit.

## Phase 2B decisions — 2026-10-07

### ADR-021: Keep fuzzy detection independent from exact detection

`FuzzyRule`, its candidate finder and detector form a separate engine. `ExactRule`
remains specific to exact matching. This prevents approximate thresholds and
metrics from weakening the exact engine's semantics or public API.

### ADR-022: Define similarity per field and omit a global score

Each `FuzzyFieldRule` supplies its own normalizer, metric, threshold and
required/optional flag. A result includes `FieldComparison` values with the
metric ID, numeric similarity and threshold outcome. The package does not weight,
average or label these values and never interprets them as probabilities.

### ADR-023: Use code-point normalized Levenshtein with a hard length ceiling

For non-empty strings, similarity is `1 - d(a,b)/max(|a|,|b|)`. Unicode strings
are validated and split into code points. Empty/empty returns `1.0` at metric
level; missing normalization prevents it becoming evidence. The implementation
uses two dynamic-programming rows, `O(m*n)` time and `O(min(m,n))` memory. A
configurable maximum rejects excessive input before the calculation.

### ADR-024: Make SQL equality blocking mandatory and explicit

Fuzzy discovery requires at least one `SqlBlock`, and every block needs a
non-missing normalized source value. Missing block values yield no candidates
and issue no candidate/schema query. The strategy can cause false negatives;
that tradeoff is visible rather than hidden behind a table scan.

### ADR-025: Treat contradictions as separate, non-decisive evidence

Contradiction rules operate only when both normalized values are present and
different. Their fields are unique and cannot also be fuzzy comparison fields,
which prevents one outcome overwriting another. Contradictions neither reject a
candidate nor authorize a merge, risk class or definitive identity conclusion.

### ADR-026: Keep reports privacy-minimal and processing bounded

Reports include model types, identifiers, stable rule IDs, outcomes, metric
metadata and evidence codes. They exclude raw or normalized compared values.
Candidate, comparison, fetch and report-batch limits are explicit positive values.

### Phase 2B pending decisions

- Domain-specific threshold validation, precision/recall evaluation and presets.
- Additional metrics or normalization policies, including grapheme/Unicode
  normalization choices.
- Composite or alternative blocking, indexing guidance and real data benchmarks.
- MySQL/PostgreSQL execution, collation behavior and query-plan verification.
- Snapshot/resume policies and whether comparison-budget telemetry is needed.
- Declarative configuration and long-term public API stability.
- License, commercial model and eventual Free/Pro boundaries.

## Phase 2C decisions — 2026-10-07

### ADR-027: Reuse the classification contract through composition

`DeterministicRiskClassifier` implements the existing
`RiskClassifierInterface::classify(ComparisonResult)`. `RiskClassification` gains
optional policy ID, rule ID and evidence constructor arguments at the end, so
existing calls remain valid. Exact and fuzzy comparison algorithms are unchanged.

### ADR-028: Make declared order the only rule priority

Rules are evaluated from first to last and the first complete match wins. This
handles overlap without numeric priorities, hidden severity or universal labels.
An empty rule list is valid and always produces the explicitly configured
fallback label.

### ADR-029: Keep conditions conjunctive and field-specific

A classification rule can require comparison rule IDs, exact field outcomes,
per-field similarity thresholds, contradiction state, evidence counts/codes and
present/missing fields. Thresholds come from the consumer. No weighting,
aggregation or global score is introduced.

### ADR-030: Preserve contradictions as evidence

Contradiction evidence remains in `ComparisonResult` and is copied as a safe code
into the classification. A consumer can place a contradiction-review rule first,
but the package does not mandate that policy or discard high-similarity evidence.

### ADR-031: Return classified copies of match/report DTOs

`DuplicateMatchClassifier` offers optional integration without adding classifier
dependencies to either detector. It constructs new readonly DTOs and performs no
database access. This keeps existing detector construction and output compatible.

### Phase 2C pending decisions

- Declarative config-to-policy resolution and configuration versioning.
- A stable public evidence-code registry and deprecation policy.
- Domain evaluation of labels, ordering, thresholds and missing-data treatment.
- Whether policies need trace output for failed rules in a later phase.
- MySQL/PostgreSQL execution and the wider PHP version matrix.
- License, release process and final Free/Pro boundaries.

## Phase 3A decisions — 2026-10-07

### ADR-032: Use declarative engines with a minimal scoped request API

Rules, blocking and limits are compiled from PHP configuration. The fluent layer
only selects a configured model and requires an authorized scope. This is easier
to validate than building engine rules through mutable method calls and keeps
candidate blocking explicit.

### ADR-033: Require scope for every public detection call

There is no default `Model::query()` execution path. `detect()` throws before
querying unless `scope()` supplied a callback returning the correct model
Builder. The package preserves that Builder and its scopes; authorization itself
belongs to the consumer.

### ADR-034: Keep the declarative registry closed and query-free

Configuration supports the built-in `email`, `text`, `phone` normalizers and
`normalized_levenshtein` metric. Unknown aliases fail. Arbitrary classes are not
instantiated from configuration. Compilation constructs rule objects but does
not instantiate models, inspect schemas or connect to a database.

### ADR-035: Stream engines independently without deduplication

Exact reports precede fuzzy reports. Cross-engine duplicates are retained because
their evidence differs. A first implementation used `yield from`; overlapping
integer keys caused report replacement under `iterator_to_array()`. The
orchestrator now re-yields each report with fresh sequential keys.

### ADR-036: Add a facade only after binding a real manager

The provider registers `DetectorConfigResolver` and `DuplicateDetectorManager`
as lazy singletons. `Facades\DuplicateDetector` resolves the manager. Existing
low-level APIs remain unchanged and usable without the facade.

### Phase 3A pending decisions

- Named profiles per model and per-call selection of configured profiles.
- Extensible alias registration without unsafe config-time class construction.
- Cross-engine deduplication or grouping semantics, if a use case requires it.
- Config caching/versioning and stable public API guarantees.
- Production policy/threshold evaluation and database benchmarks.
- License, pricing, distribution and final Free/Pro boundaries.


## Phase 3C.2A decisions — 2026-10-09

### ADR-037: Enforce the exact ceiling inside keyset discovery

The finder owns the finite ceiling and sizes every page to the remaining
allowance. `batch_size` remains a memory and round-trip control. The default is
1000: finite to remove implicit unbounded work and large enough to preserve
ordinary small-block behavior. It is not presented as universal.

`CandidateFinderInterface::find()` keeps its signature. The exact finder gains an
optional constructor limit, while `ExactDuplicateDetector::detect()` gains an
optional final `maxCandidates` argument. Existing calls remain valid and finite.

### ADR-038: Report terminal completeness conservatively

`DuplicateReport` gains optional completion metadata. Retaining one match batch
lets the final exact report carry status with bounded memory. Reaching the limit
always means `max_candidates_reached`, including a block that might end on that
row. A probe query was rejected because it would add work after the boundary.
An empty terminal report is emitted only when truncation would otherwise be
invisible.

Fuzzy comparison, fuzzy limits and classification rules remain unchanged.

## Phase 3C.3 decisions — 2026-10-09

### ADR-039: License the Free package under MIT

The owner approved MIT for `lzk/laravel-duplicate-detector`. The project brief
identifies the package as belonging to LZK Tools, and no alternative contributor
or copied third-party source attribution was found in the source tree. The
standard MIT notice therefore names `LZK Tools` as the 2026 copyright holder.
Runtime dependency metadata reports MIT, BSD-3-Clause, Apache-2.0, or permissive
choices in multi-license declarations; no Composer advisory was reported.

A future Pro product will be a separate package with separately approved
commercial terms. This decision does not select or implement that license.

### ADR-040: Separate mandatory and experimental CI evidence

Rows already executed locally are mandatory: PHP 8.2/Laravel 12 current and
minimum, PHP 8.4/Laravel 12 current, and PHP 8.4/Laravel 13 current and minimum.
PHP 8.3 and PHP 8.5 rows for valid Laravel majors are experimental until GitHub
executes them successfully and the owner promotes them. PostgreSQL/MySQL services
are omitted because the current suite is wired to SQLite and cannot honestly
verify those engines.

### ADR-041: Keep distribution and publication distinct

Composer archive exclusions and Git export rules omit development-only files.
A locally inspected archive is preparation evidence only. A tag, GitHub release,
Packagist entry, support promise, and enabled private vulnerability channel each
require separate evidence and owner approval.
