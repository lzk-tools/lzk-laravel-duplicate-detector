# Performance and benchmark plan

No benchmark was executed in this audit, and this document contains no invented throughput or latency result.

## Complexity and current controls

Exact discovery uses an equality block and keyset pagination through `lazyById()`. Its memory use is bounded by page/report batches, but total work is proportional to the number of rows in the block and currently has no overall ceiling.

Fuzzy discovery has `max_candidates`, `fetch_batch_size`, and `max_comparisons`. Normalized Levenshtein operates on Unicode code points with time complexity **O(m × n)** and memory complexity **O(min(m, n))**. The configured `max_code_points` bounds each input; with the default 1024 cap, one worst-shape comparison can require roughly 1,048,576 dynamic-programming cell updates. That is a complexity bound, not a measured duration.

Email/text/phone normalization is linear in input length plus allocation and Unicode-library cost. Schema column listing is requested once per effective finder run and intentionally is not cached globally, avoiding stale metadata in migrations or long-lived workers.

## Findings

| ID | Severity | Concrete evidence | Impact | Recommendation | Blocks first publication |
|---|---|---|---|---|---|
| PERF-001 | High | Exact `batch_size` limits page size only; no maximum candidates/comparisons exists. | Broad blocks produce unbounded total work relative to table contents. | Add a total ceiling and an explicit truncated/exhausted result state before production positioning. | **Yes for production-ready publication** |
| PERF-002 | High | Blocking is equality-based, but package docs do not provide query-plan examples or precise composite-index guidance for tenant plus block plus key traversal. | Large tables can sort/scan extensively even though the logical query is blocked. | Recommend an index beginning with authorization/tenant columns and blocking columns, followed by the paging key where supported; require consumers to confirm with their engine's `EXPLAIN`. | No for preview; yes before scale claims |
| PERF-003 | Medium | Fuzzy worst-case CPU is `max_comparisons × O(max_code_points²)`; limits are independent and consumer-configured. | Individually valid high limits can create excessive CPU time. | Benchmark combined limit profiles, document safe starting envelopes from measured data, and consider a future operation budget/cancellation hook. | No |
| PERF-004 | Medium | `getColumnListing()` executes on each effective finder run; there is no process-global cache. | Repeated small detections incur metadata-query overhead. | Measure separately. If material, use an opt-in, connection/table-keyed cache with explicit invalidation and bounded lifetime; do not add unsafe global mutable state. | No |
| PERF-005 | Medium | Keyset paging is efficient with a compatible index, but string/UUID order follows database collation and concurrent changes can affect later pages. | Throughput and repeatability vary by engine and workload. | Benchmark integer and UUID/string keys per engine, record collation, and use a controlled snapshot for repeatability tests. | No |
| PERF-006 | Medium | Blocking intentionally trades recall for bounded work. There is no block-cardinality telemetry. | Operators cannot see when a rule is too broad or excludes expected candidates. | Measure candidate counts, truncated runs, query count, and per-stage duration without recording field values. | No |
| PERF-007 | Low | Only required key/comparison columns are selected and candidates are processed incrementally. | Memory is materially safer than full-table hydration, but peak usage has not been measured. | Include peak RSS and hydrated rows per page in the benchmark harness. | No |

## Reproducible benchmark design

Create a separate, non-production benchmark application. Pin the package commit, PHP version, Laravel version, Composer lock, OS, CPU/memory allocation, database image/version, collation, and configuration. Generate deterministic fictitious records from a recorded seed.

Dataset sizes: 10,000; 100,000; and 1,000,000 rows where infrastructure permits. For each size create:

- selective blocks of approximately 1, 10, and 100 candidates;
- a deliberately broad block (1% and 10% of the table);
- no-match and source-only blocks;
- integer and UUID/string primary keys;
- indexed and intentionally unindexed variants;
- short, medium, and maximum-length fuzzy strings;
- contradiction and missing-value mixes.

Workloads:

1. exact detection with each block cardinality and page size;
2. fuzzy detection across candidate/comparison limits;
3. classification enabled and disabled;
4. cold and warm metadata paths;
5. concurrent readers plus controlled inserts/updates between pages;
6. tenant and global-scope predicates with OR conditions.

Record p50/p95/p99 wall time, CPU time, peak RSS, SQL query count, rows examined/read where the engine exposes it, candidates compared, reports emitted, and whether a limit truncated the run. Capture `EXPLAIN` plans with bindings replaced by fictitious examples. Run warmups, then at least 10 measured repetitions; report median and dispersion, not only the best run.

## Acceptance criteria process

Do not set numeric release thresholds before baseline measurements and product workload requirements exist. After measurement, approve separate budgets for interactive requests and background jobs. A regression gate should compare the same pinned environment and fail only beyond a documented tolerance large enough to account for normal variance.
