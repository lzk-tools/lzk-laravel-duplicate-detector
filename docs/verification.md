# Verification record

## Phase 2A — 2026-10-07

All work was performed in /var/www/lzk-laravel-duplicate-detector.
The final installed development resolution is Laravel 12.69.3.

### Executed matrix

| PHP | SQLite | Laravel | Testbench | PHPUnit | Result |
| --- | --- | --- | --- | --- | --- |
| 8.4.24 | 3.46.1 | 12.69.3 | 10.12.0 | 11.5.57 | 81 tests, 212 assertions, pass |
| 8.4.24 | 3.46.1 | 13.35.0 | 11.3.0 | 11.5.57 | 81 tests, 212 assertions, pass |

Both combinations passed Larastan 3.13.0 / PHPStan 2.3.0 at level 8 and
PHP_CodeSniffer 3.13.6 using PSR-12 without errors or warnings.
Composer 2.10.3 validated composer.json with the expected missing-license warning.
It also reports a fallback root version because no VCS metadata/release exists.
The local lockfile was refreshed after adding the mbstring requirement.

No PHP runtime other than 8.4.24, MySQL/PostgreSQL integration, lowest-version
matrix or independent consumer installation was executed. No production quality,
performance bound, full recall or commercial readiness is claimed.

### SQLite setup in this environment

PDO SQLite was initially unavailable. The apt metadata pointed to a package URL
returning HTTP 404. The same official Debian PHP 8.4.24 module was obtained from
the Debian package pool and extracted solely into .tools/sqlite.
Only pdo_sqlite.so is loaded. No global PHP ini file or installed package changed.

The ignored project-local configuration is:
.tools/sqlite/conf.d/sqlite.ini, pointing to
.tools/sqlite/extracted/usr/lib/php/20240924/pdo_sqlite.so.

Normal development machines should provide PDO SQLite in their PHP test runtime.
Do not distribute this environment-specific binary with the package.

### Commands actually executed

From the project directory in WSL:

```sh
PHP_INI_SCAN_DIR=:/var/www/lzk-laravel-duplicate-detector/.tools/sqlite/conf.d \
  php .tools/composer.phar check

php .tools/composer.phar validate

PHP_INI_SCAN_DIR=:/var/www/lzk-laravel-duplicate-detector/.tools/sqlite/conf.d \
  php .tools/composer.phar check-platform-reqs
```

The check script runs PHPUnit, Larastan/PHPStan and PHP_CodeSniffer.
The matrix used the existing exact Laravel 12/13 dependency snapshots, refreshed
for the current manifest, and composer install to switch between them.
Updated snapshots are .tools/phase2a-laravel12.lock and
.tools/phase2a-laravel13.lock. Composer's cache was redirected into .tools for
the Phase 2A dependency operations.

### Behavioral coverage

- UTF-8 email lowercase/trim, Unicode text spacing and phone separator removal.
- Idempotence, null/empty values, leading zeros, invalid UTF-8/options/input types.
- No email provider rules, country assumptions or automatic extension stripping.
- Exact match, ordinary difference, missing evidence and explicit contradiction.
- Invalid rule configuration, missing SQL columns and partially loaded models.
- Tenant restriction, active global scope, soft deletes, explicit removed scopes.
- Source exclusion, top-level OR grouping, caller filter retention.
- No changes to original Builder, query model, eager loads or dirty source attributes.
- Repeated deterministic reports, multiple candidate/report batches and no matches.
- Multiple mandatory SQL blocks and no candidate query for a missing block.
- Rejection of unsupported query shapes, mismatched models/connections and invalid batches.
- Custom string primary keys with leading zeros.
- Only key/comparison columns selected; no raw field values in reports.
- No retrieved model events or eager-loading execution during candidate hydration.
- SQLite query_only mode during detection, SELECT-only observed queries,
  and fixture contents unchanged after detection.

The fixture tables and rows are created before query_only mode using Schema and
INSERT in memory. There are no migration files and no consumer database accesses.
The package never calls model save, merge, update or delete.

### Errors encountered and resolved

- Initial PDO SQLite download URL returned 404; the official package-pool URL worked.
- A PHPUnit helper name collided with its final matches() method; renamed.
- Static analysis identified an unsupported protected Query Builder property and
  a connection-interface method mismatch; corrected using the installed API.
- Public input PHPDoc assumptions conflicted with runtime defensive validation;
  analysis now treats PHPDoc inputs as untrusted, without suppressing errors.
- Negative test fixtures and array/match typing required corrections.
- PSR-12 found long lines; corrected.
- Composer initially reported a stale local lockfile after the new extension
  requirement; refreshed without changing the package versions.

There are no unresolved PHPUnit, Larastan or PSR-12 failures.
License/root-version warnings remain intentional.
The preceding Phase 1 record follows as historical evidence.

---

# Phase 1 verification

Date: 2026-10-07. Environment: WSL, PHP CLI 8.4.24.
Composer used: locally downloaded 2.10.3, under ignored .tools.
The preinstalled Composer 2.5.5 emitted PHP 8.4 deprecation notices and was not used
for dependency resolution. The first local download returned HTTP 500; retry succeeded.

## Executed matrix

| PHP | Laravel | Testbench | PHPUnit | Result |
| --- | --- | --- | --- | --- |
| 8.4.24 | 13.35.0 | 11.3.0 | 11.5.57 | 13 tests, 40 assertions pass |
| 8.4.24 | 12.69.3 | 10.12.0 | 11.5.57 | 13 tests, 40 assertions pass |

Both resolutions used Larastan 3.13.0, PHPStan 2.3.0 and PHP_CodeSniffer 3.13.6.
Both passed analysis at level 8, PSR-12 checks and composer check-platform-reqs.
Composer reported no known security vulnerability advisories for either resolution.

Only PHP 8.4.24 was actually executed. Other PHP versions, every intermediate
framework version, lowest dependencies and an independent consumer installation
were not tested. No detection accuracy or production suitability is claimed.

## Commands

```sh
php .tools/composer.phar update --no-interaction --prefer-dist
php .tools/composer.phar check
php .tools/composer.phar validate
php .tools/composer.phar check-platform-reqs

php .tools/composer.phar update --with orchestra/testbench:^10.0 --with laravel/framework:^12.0
php .tools/composer.phar check
php .tools/composer.phar check-platform-reqs
php .tools/composer.phar audit
```

Local verification lock snapshots are .tools/laravel12.lock and
.tools/laravel13.lock. They are ignored development artifacts.
The final installed environment is Laravel 12.69.3 with its composer.lock.

## Coverage of scaffold behavior

- Provider registration and successful boot with an empty database connection list.
- Configuration publication through the real vendor:publish command, file comparison
  and cleanup/restoration inside the Testbench sandbox.
- Consumer configuration precedence.
- Composer autoload for all public interfaces/classes and discovery metadata.
- Basic DTO construction, record key preservation and separation of contradictions.
- Invalid score/metric combinations, non-finite numbers and blank classifications.

Testbench registers the provider explicitly; discovery metadata is tested separately.
No end-to-end consumer installation is claimed.

## Errors encountered and resolved

- Publication path assertion exposed an unnormalized src/../config path; the provider
  now uses dirname(__DIR__) for stable paths.
- Static analysis found nullable Testbench application and PendingCommand union types;
  tests now explicitly assert these preconditions.
- A retained PendingCommand delayed command execution; the test now calls run()
  before checking the published file.
- PSR-12 found test naming and multiline formatting violations; corrected.
- Files initially written through the Windows/WSL bridge had root ownership;
  ownership was corrected to the original directory owner before formatting.

## Remaining warnings

composer.json validates, with the expected missing-license warning.
Composer also reports an inferred root version of 1.0.0 because no Git metadata
or release exists. That fallback is not a release declaration.
There are no unresolved test, static-analysis or PSR-12 failures.
# Phase 2A.1 audit — 2026-10-07

Environment: WSL, PHP 8.4.24, SQLite 3.46.1 and Composer 2.10.3.

| Laravel | Testbench | PHPUnit | Result |
| --- | --- | --- | --- |
| 12.69.3 | 10.12.0 | 11.5.57 | 89 tests, 245 assertions, pass |
| 13.35.0 | 11.3.0 | 11.5.57 | 89 tests, 245 assertions, pass |

Both resolutions passed Larastan 3.13.0 / PHPStan 2.3.0 at level 8 and
PHP_CodeSniffer 3.13.6 with PSR-12. Composer validation passed with the existing,
intentional missing-license warning. Platform requirements passed on PHP 8.4.24.

The audit inspected the installed framework sources. Laravel 12.69.3 source
reference was `58ea544a2a80dc03c168e13a5dc9a1d176a88717`; Laravel 13.35.0 was
`a413a9da94884e5e9d58f4e6612884f712827804`. Both Query Builder implementations
perform keyset pages with cloned queries and `get()`. Laravel 13 has additional
limit/offset and key-extraction handling that does not affect the finder's rejected
limit/offset query shapes.

Added evidence covers integer, zero-padded string and UUID primary keys; enabled
and explicitly removed global scopes; tenant filters; grouped OR predicates;
original/scope/package binding order; multiple pages; zero candidates; schema
query count; no schema lookup when a block is missing; no accessors, retrieved
events or eager loading; and absence of detector INSERT/UPDATE/DELETE statements.

A controlled external INSERT between keyset pages demonstrates that a greater key
can appear later in the same run. This is test setup representing another writer,
not a package write. The consistency limit is documented rather than hidden.

SQLite was the only executed database. PHP has PDO MySQL and PDO PostgreSQL
extensions, but no MySQL/PostgreSQL clients or servers were available. Their
collations, metadata queries, plans, transaction isolation and UUID behavior remain
pending. No PHP runtime other than 8.4.24 was executed.

Composer still reports an inferred root version because the directory has no VCS
metadata or release. No commit, publication or migration was created.

---


# Phase 2B verification — 2026-10-07

## Executed environment

- PHP 8.4.24.
- PHPUnit 11.5.57.
- Laravel Framework 12.69.3 with Orchestra Testbench 10.12.0.
- Larastan 3.13 / PHPStan 2.3 and PHP_CodeSniffer 3.13.
- SQLite 3.46.1 in memory through the project's local PDO SQLite extension.

Laravel 13 results are recorded after the dependency-matrix run below. MySQL and
PostgreSQL were not executed because no server/client was available. No claim is
made for unexecuted PHP runtimes or database engines.

## Commands

```bash
PHP_INI_SCAN_DIR=:$PWD/.tools/sqlite/conf.d vendor/bin/phpunit
vendor/bin/phpstan analyse --no-progress
vendor/bin/phpcs
php .tools/composer.phar validate --strict
```

The initial Laravel 12 PHPUnit run passed with 122 tests and 372 assertions.
After adding the rule-field collision regression test, the final counts are
recorded below. Larastan passed after replacing one redundant deterministic
assertion that PHPStan correctly identified as statically certain. PHPCS first
reported a 122-character condition; splitting the expression resolved it.
Composer validation is valid with the expected warning that the package license
is not yet specified. Composer also reports the normal root-version warning in
this non-versioned workspace.

## Behavioral coverage

Tests cover exact equality, small differences, accents, Unicode code points,
empty/null values, long strings and the configured length ceiling. They cover
explicit contradiction beside high similarity, low similarity without
contradiction, deterministic reports and privacy-safe evidence.

SQLite integration tests cover tenant and global-scope isolation, original
Builder preservation, OR predicates and bindings, source exclusion, zero
candidates, mandatory/missing blocking, false-negative blocking behavior,
multiple fetch/report batches, hard candidate/comparison caps, raw hydration,
no events/accessors/eager loading, and observed SELECT-only SQL. The complete
legacy exact suite also runs, providing compatibility coverage for that engine.

SQLite does not prove production collation, metadata cost, execution plans,
concurrent consistency or UUID ordering on PostgreSQL/MySQL. The fuzzy keyset
iterator offers no snapshot and has the concurrency limits documented for the
exact iterator in ADR-019.

## Final matrix results

| Framework | Testbench | PHPUnit result | Larastan/PHPStan | PHPCS |
| --- | --- | --- | --- | --- |
| Laravel 12.69.3 | 10.12.0 | 123 tests, 372 assertions | Passed, no errors | Passed, no violations |
| Laravel 13.35.0 | 11.3.0 | 123 tests, 372 assertions | Passed, no errors | Passed, no violations |

Both rows used PHP 8.4.24 and SQLite 3.46.1 in memory. The dependency
installation was returned to Laravel 12.69.3/Testbench 10.12.0 after the matrix.
Composer validation was executed on that restored environment and the only
package-level warning is the intentionally undecided license.

# Phase 2C verification — 2026-10-07

## Initial executed environment and results

- PHP 8.4.24.
- Laravel 12.69.3 / Orchestra Testbench 10.12.0.
- PHPUnit 11.5.57: 140 tests, 422 assertions, all passing.
- Larastan 3.13.0 / PHPStan 2.3.0: no errors.
- PHP_CodeSniffer 3.13.6 with PSR-12: no violations.
- SQLite 3.46.1 in memory for integration tests.

The classification suite covers exact comparisons, high and low fuzzy
similarity, multiple evidence codes, contradictions, high similarity with a
contradiction, missing fields, overlapping rules, deterministic order, explicit
fallback, invalid configuration and absence of compared values in output.

An integration test classifies reports produced by the fuzzy detector, confirms
that the contradiction remains visible and inspects the SQL log for mutation
statements. A separate test confirms classification alone performs no database
statements. The pre-existing exact/fuzzy suites run unchanged.

The first test run exposed two incorrect object-identity assertions and a missing
SQLite connection in the read-only test. The assertions were corrected to compare
value equality and the test now defines its isolated in-memory connection. No
runtime classification defect was found in that run.

Laravel 13 matrix results and final Composer validation are recorded below after
the dependency switch. PostgreSQL/MySQL and other PHP runtimes are not executed
in this phase.

## Final dependency matrix

| Framework | Testbench | PHPUnit | Larastan/PHPStan | PHPCS |
| --- | --- | --- | --- | --- |
| Laravel 12.69.3 | 10.12.0 | 140 tests, 422 assertions | Passed | Passed |
| Laravel 13.35.0 | 11.3.0 | 140 tests, 422 assertions | Passed | Passed |

Both rows used PHP 8.4.24 and SQLite 3.46.1 in memory. Composer validation
passed with only the expected missing-license and unversioned-root warnings. The
workspace was restored to Laravel 12.69.3/Testbench 10.12.0 after the matrix.

# Phase 3A verification — 2026-10-07

The Laravel 12 development run initially passed behavior tests but exposed one
orchestration defect: `yield from` preserved duplicate numeric keys from exact
and fuzzy report iterators, allowing `iterator_to_array()` to overwrite earlier
reports. `ConfiguredDetector` now re-yields reports with sequential keys and a
regression test covers combined engines.

Coverage added for container singletons, facade resolution, valid exact-only,
fuzzy-only and combined configuration, classification, tenant/global scopes,
Builder preservation, limits, mandatory scope, rejected models, invalid aliases
and rules, query-free configuration validation, read-only SQL and privacy-safe
results. Existing low-level engine tests remain unchanged.

Final Laravel 12/13 counts and tool results are recorded below after executing
the dependency matrix. Only SQLite is available for database integration.

## Final dependency matrix

| Framework | Testbench | PHPUnit | Larastan/PHPStan | PHPCS |
| --- | --- | --- | --- | --- |
| Laravel 12.69.3 | 10.12.0 | 151 tests, 474 assertions | Passed | Passed |
| Laravel 13.35.0 | 11.3.0 | 151 tests, 474 assertions | Passed | Passed |

Both rows used PHP 8.4.24 and SQLite 3.46.1 in memory. `composer validate
--strict` passed with the expected missing-license and unversioned-root warnings.
The workspace was restored to Laravel 12.69.3/Testbench 10.12.0.


# Phase 3C.2A verification — 2026-10-09

Coverage was added for fewer, exactly, and more candidates than the limit;
non-matches consuming the allowance; different page sizes; tenant and global
scopes; grouped OR predicates and bindings; integer, string, and UUID keys;
partial and empty terminal reports; configuration validation; public API use;
read-only SQL; and absence of an extra candidate page after the ceiling.

The first focused run exposed test query counting that included source loading
and explicit null being treated like an absent config key. Source loading was
moved before query-log reset, and the resolver now distinguishes absent
`max_candidates` from explicit null. No comparison algorithm changed.

Final Laravel 12 and 13 results are recorded after executing the dependency
matrix below.


## Final dependency matrix

| Framework | Testbench | PHPUnit | Larastan/PHPStan | PHPCS |
| --- | --- | --- | --- | --- |
| Laravel 12.69.3 | 10.12.0 | 173 tests, 543 assertions — passed | Passed, 64 files | Passed |
| Laravel 13.35.0 | 11.3.0 | 173 tests, 543 assertions — passed | Passed, 64 files | Passed |

Both rows used PHP 8.4.24 and SQLite in memory. Laravel 12 was restored after
the Laravel 13 run. PostgreSQL, MySQL and other PHP versions were not executed.
No benchmark timing or throughput claim was produced.

# Phase 3C.2C compatibility verification — 2026-10-09

The available runtimes were PHP 8.2.0 on Windows and PHP 8.4.24 in WSL. PHP
8.3 and 8.5 were not installed. Composer 2.10.3 from the package tools was used
for isolated resolution; global Composer 2.5.5 emits PHP 8.4 deprecation noise.

Executed package rows all passed PHPUnit (181 tests, 568 assertions),
Larastan/PHPStan, PHPCS/PSR-12 and non-development platform checks:

| PHP | Laravel | Testbench | Dependency mode | Database |
| --- | --- | --- | --- | --- |
| 8.2.0 | 12.69.3 | 10.12.0 | current | SQLite via Windows PDO |
| 8.2.0 | 12.69.0 | 10.0.0 | prefer-lowest/prefer-stable | SQLite via Windows PDO |
| 8.4.24 | 12.69.3 | 10.12.0 | current | SQLite 3.46.1 in memory |
| 8.4.24 | 13.35.0 | 11.3.0 | current | SQLite 3.46.1 in memory |
| 8.4.24 | 13.30.0 | 11.0.0 | prefer-lowest/prefer-stable | SQLite 3.46.1 in memory |

Laravel 13 on PHP 8.2 was not attempted because its platform requirement is PHP
8.3 or newer. The Laravel 12 demo passed 8 tests and 33 assertions on PHP 8.4.24
after loading the project-local PDO SQLite extension. Its first invocation without
that extension failed with `could not find driver`; this is an environment setup
failure and was corrected without changing application or package code.

PostgreSQL 16 was running and accepted connections on localhost:5432, but an
unauthenticated connection was rejected and no isolated test credentials were
available. No MySQL/MariaDB service or port 3306 listener was present. Docker was
not available inside the WSL distribution. These engines are infrastructure
blocked, not verified. No service, container or database was created or changed.

`composer validate --strict --no-check-publish` continues to exit 1 solely for
the deliberately undecided root license. Dependency resolution reported no known
security advisories. The checkout was restored to Laravel 12.69.3 and Testbench
10.12.0 after the matrix. No package algorithm or public contract changed.

# Phase 3C.3 distribution infrastructure verification — 2026-10-09

The owner approved MIT for the Free package. The standard license names LZK Tools,
which the project brief identifies as the package owner. No Git repository history,
alternative contributor attribution, or copied third-party source was found.
Runtime dependency metadata contains MIT, BSD-3-Clause, Apache-2.0, and dual-license
entries with permissive choices. `composer audit --locked` reported no advisories.

Two GitHub Actions workflows were prepared but not executed on GitHub because no
Git repository or remote exists. Symfony YAML lint accepted both files. Mandatory
compatibility rows reproduce the locally verified matrix; PHP 8.3 and 8.5 rows are
experimental. Database services are omitted until tests can select PostgreSQL or
MySQL honestly.

`composer validate --strict --no-check-publish` passed after adding MIT. The first
local Composer archive contained `vendor/` and `composer.lock` because this checkout
has no VCS metadata. Explicit Composer archive exclusions corrected the issue. The
regenerated candidate contained 62 files, included LICENSE/README/CHANGELOG/config/
and `src/`, and excluded vendor, the lock, workflows, tools, tests, caches and dev
configuration. This archive was inspected locally only and was not published.

# Phase 3C.4 local 0.1.0 candidate verification — 2026-10-09

This phase prepares a preliminary local `0.1.0` candidate with no release date,
tag, commit, remote, publication, or production-support claim.

The external workflow references were resolved directly from their official Git
repositories and pinned to full commits: `actions/checkout` v4 at
`11d5960a326750d5838078e36cf38b85af677262` and `shivammathur/setup-php` v2 at
`d52fc211a0436f8839a587b3b5b8efddab4744ea`. Local Symfony YAML lint validates
syntax only; GitHub has not executed either workflow.

A fresh Laravel application (`laravel/laravel` 12.12.2 resolving Framework
12.69.3) was created under `/tmp`. Composer's `artifact` repository rejected the
tar because its internal library metadata intentionally has no fixed `version`.
The same tar was then installed through a local Composer `package` repository
that supplied version `0.1.0`, requirements, PSR-4 autoload and discovery metadata.
No path repository or symlink was used. Package discovery, configuration publishing,
license/config/provider presence and extracted-package installation were verified.

The isolated consumer suite passed 7 tests and 35 assertions covering exact and
fuzzy matches, contradiction classification, tenant scope, mandatory scope before
queries, partial exact metadata, observed absence of detector writes, privacy-safe
results and the Artisan command. The clean application's initial create-project
migration warned `could not find driver` before the project-local PDO SQLite
extension was enabled; tests then passed on in-memory SQLite without modifying an
existing database.

Package PHPUnit, Larastan/PHPStan, PHPCS, Composer validation/audit, workflow YAML,
archive contents, SHA-256 and the final extracted artifact are recorded as local
evidence only. GitHub controls, private reporting, PHP 8.3/8.5, PostgreSQL/MySQL,
and production support remain unverified.
