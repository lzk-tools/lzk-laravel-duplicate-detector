# Security review

**Date:** 2026-10-09
**Scope:** built-in package paths and the Laravel 12 demo. This is a source review, not a penetration test.

## Trust boundaries and verified guarantees

The recommended API refuses detection until `scope()` has been called. It creates a fresh Eloquent builder, applies the consumer callback, and passes a clone into each finder. Both finders apply global scopes, preserve original where clauses and bindings, group original predicates before adding blocking clauses, exclude the source key, reject query shapes that could change the selected table or paging semantics, and use bound values. Built-in execution issues SELECT and schema-introspection queries; tests assert that INSERT, UPDATE, and DELETE are absent.

These guarantees apply to package-owned implementations. They do not prove authorization: the consumer callback can provide an insufficient tenant predicate or deliberately remove global scopes. They also do not prevent side effects inside consumer callbacks, custom normalizers, custom models, database triggers on SELECT-like functions, query listeners, or replacement container bindings. Database-enforced read-only credentials remain the strongest operational control.

## Findings

| ID | Severity | Concrete evidence | Impact | Recommendation | Blocks first publication |
|---|---|---|---|---|---|
| SEC-001 | High | `ConfiguredDetector::detect()` checks only that a scope callback was supplied and that its returned builder targets the configured model. It cannot prove a tenant predicate, and consumer code can call `withoutGlobalScopes()`. | A consumer can accidentally search across tenants while satisfying the API's structural requirement. | State that `scope()` is an authorization boundary supplied by the application. Show tenant-policy examples and add application-level tests for every configured model. Consider a future opt-in scope assertion hook, without pretending SQL inspection proves authorization. | No if documented prominently; yes if marketed as automatic tenant isolation |
| SEC-002 | High | `ExactCandidateFinder` requires equality blocking and paginates, but has no total-candidate ceiling. `batch_size` is only the page size. | A low-selectivity block can cause an availability incident or unexpected database load. | Add an explicit total limit and exhaustion signal in a correction phase. Require indexed, selective blocks and monitor block cardinality meanwhile. | **Yes for production-ready publication** |
| SEC-003 | Partially resolved (High) | `SECURITY.md` documents version status, report contents, coordinated disclosure and limitations, but no private channel exists. | Expectations are documented; safe submission remains unavailable. | Owner must configure and publish a monitored private channel before release. | **Yes** |
| SEC-004 | Medium | Column, key, and table names are checked against a simple identifier pattern; configured values use query bindings. Schema-qualified table names, joins, unions, groupings, selected columns, locks, limits, offsets, and query callbacks are rejected. | Identifier injection was not found in built-in rules. The restriction also prevents legitimate schema-qualified deployments. | Retain validation and document the simple-identifier limitation. Add database-specific quoting support only with dedicated tests. | No |
| SEC-005 | Medium | Built-in DTO creation records identifiers, model classes, field names, rule IDs, reason codes, outcomes, and similarity values, but not raw/normalized field values. Public DTO constructors accept caller-supplied evidence strings without enforcing a safe vocabulary. | Direct advanced users or custom extensions can place personal data in reports; identifiers themselves can also be sensitive. | Describe the privacy guarantee as applying to built-in engines. Recommend opaque rule/reason codes, output authorization, minimization, and redaction at presentation boundaries. Consider typed evidence in a future major-compatible design. | No |
| SEC-006 | Medium | “Read-only” is enforced by package code and tests, not by the database. Schema inspection queries are also executed. | Custom callbacks or bindings can write; compromised application credentials retain write capability. | Recommend a read-only database role/connection for high-assurance deployments and phrase the claim as “package-owned paths issue no DML.” | No |
| SEC-007 | Medium | Original builder predicates and their bindings are cloned and nested before package blocking predicates. Tests cover OR conditions and binding preservation. | The current implementation prevents an appended `OR` from escaping the authorized predicate group. Future query refactors could regress this. | Keep SQL-and-bindings regression tests for each supported Laravel major and DB engine. | No |
| SEC-008 | Medium | Global scopes are applied before conversion to the base query. Hydration uses raw attributes on a fresh model clone, avoiding retrieved events, accessors, and eager loading in current tests. | Current built-in iteration avoids common model side effects, but custom casts are relevant if consumers inspect hydrated candidates elsewhere. | Preserve the current raw-hydration contract and regression tests; document that detector field reads use raw originals. | No |
| SEC-009 | Low | Current locked dependency audit reported zero advisories and no abandoned packages. `composer licenses --no-dev` shows permissive runtime licenses. Library consumers resolve their own dependency graph because the package lock is not authoritative for them. | A clean development lock does not guarantee a consumer's resolved versions remain advisory-free. | Run `composer audit` in CI and at release time; enable dependency update alerts; publish security fixes promptly. | No |
| SEC-010 | Low | Blocking, comparison, and classification IDs are consumer-defined strings exposed in reports. | IDs can leak internal terminology if applications render them directly. | Treat IDs as machine codes and map them to authorized, localized UI text. | No |

## SQL and scope analysis

The finders reject preselected columns because keyset pagination and raw hydration require the primary key and comparison fields. They reject joins and unions rather than attempting to reason about ambiguous columns. Existing `WHERE` clauses are placed in a nested condition, then blocking and source exclusion are appended with `AND`. Values are passed through Laravel bindings.

Keyset pagination provides stable forward traversal only relative to the observed key ordering. It does not create a snapshot. Concurrent inserts, deletes, or updates between pages can add, omit, or change candidates. Use an appropriate transaction isolation level or a stable replica/snapshot when a repeatable audit is required; the package currently does not manage transactions.

## Data-handling guidance

- Keep canonical blocking columns limited to the minimum needed and protect them like the source personal data.
- Do not log SQL bindings in production without redaction.
- Authorize access to report identifiers and reason codes.
- Use database credentials restricted to SELECT and required metadata operations where feasible.
- Apply rate limits, job concurrency limits, and the configured fuzzy limits.
- Validate every configured model and scope in the consuming application.
