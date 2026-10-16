# Compatibility matrix

**Audit date:** 2026-10-09

The Composer constraints are PHP `^8.2`, `illuminate/database ^12 || ^13`, and `illuminate/support ^12 || ^13`. Laravel's official [support policy](https://laravel.com/docs/12.x/releases#support-policy) lists Laravel 12 on PHP 8.2–8.5 and Laravel 13 on PHP 8.3–8.5. On the audit date Laravel 12 is in security-fix support and Laravel 13 is in active support.

## Executed matrix

| Combination | Dependency mode | Database | Result |
|---|---|---|---|
| PHP 8.2.0 + Laravel 12.69.3 + Testbench 10.12.0 | clean current resolution | SQLite (Windows PDO) | 181 tests / 568 assertions, PHPStan, PHPCS and platform requirements passed |
| PHP 8.2.0 + Laravel 12.69.0 + Testbench 10.0.0 | `--prefer-lowest --prefer-stable` | SQLite (Windows PDO) | 181 tests / 568 assertions, PHPStan, PHPCS and platform requirements passed |
| PHP 8.4.24 + Laravel 12.69.3 + Testbench 10.12.0 | current resolution | SQLite 3.46.1 | 181 tests / 568 assertions, PHPStan and PHPCS passed |
| PHP 8.4.24 + Laravel 13.35.0 + Testbench 11.3.0 | current resolution | SQLite 3.46.1 | 181 tests / 568 assertions, PHPStan, PHPCS and platform requirements passed |
| PHP 8.4.24 + Laravel 13.30.0 + Testbench 11.0.0 | `--prefer-lowest --prefer-stable` | SQLite 3.46.1 | 181 tests / 568 assertions, PHPStan, PHPCS and platform requirements passed; dependency deprecation notices were emitted |
| PHP 8.4.24 + Laravel 12.69.3 demo application | path repository, `@dev` | SQLite 3.46.1 | 8 tests / 33 assertions passed after enabling the project-local PDO SQLite extension |

This table reports executions already documented by the project. It does not turn unexecuted combinations into claims.

## Findings

| ID | Severity | Concrete evidence | Impact | Recommendation | Blocks first publication |
|---|---|---|---|---|---|
| COMP-001 | Medium | PHP 8.2 and 8.4 passed. PHP 8.3 and 8.5 were not installed and therefore were not run. | Valid declared runtime combinations remain unverified. | Add CI rows for PHP 8.3 and 8.5; keep claims limited to executed rows until then. | **Yes for the full declared claim** |
| COMP-002 | Resolved for tested framework majors | Minimum and current dependency resolutions passed for Laravel 12 and 13 in the rows above. | The tested lower/current dependency sets behave consistently. | Preserve both resolution modes in CI because resolved minima change over time. | No for the executed rows |
| COMP-003 | High | PostgreSQL 16 accepts TCP connections locally but isolated test credentials were unavailable. No MySQL/MariaDB service or port 3306 listener was found. Docker is unavailable in WSL. | Identifier quoting, collation, schema introspection, key ordering, and query-plan behavior remain unverified outside SQLite. | Run isolated credentialed PostgreSQL and MySQL CI services before listing those engines as verified. | No if support claims remain SQLite-only; **yes for PostgreSQL/MySQL claims** |
| COMP-004 | Medium | Laravel 12 and 13 have different minimum PHP versions and Testbench majors. A single unconstrained update cannot exercise every valid pair. | A green default install can hide matrix-specific failures. | Use explicit CI include rows and fail if the resolved framework major differs from the requested row. | No once CI is added |
| COMP-005 | Medium | String/UUID keyset ordering depends on database collation; concurrent changes are not snapshot-consistent. Composite keys and schema-qualified table names are unsupported. | Results and supported model shapes can differ across engines/configurations. | Document these limits and add UUID/string-key tests on every claimed DB engine. | No |
| COMP-006 | Low | Global Composer 2.5.5 emits PHP 8.4 deprecations. Project-local Composer 2.10.3 completed resolution. Minimum Laravel 13 dependencies also emit third-party PHP 8.4 deprecations during PHPStan. | No package defect is demonstrated, but noisy tooling can obscure real release failures. | Use current Composer and separately track dependency deprecations in CI. | No |

## Availability and status

- **Verified:** the five package rows and demo row above.
- **Not executed:** PHP 8.3 and PHP 8.5; no matching runtimes were installed.
- **Incompatible by platform:** Laravel 13 on PHP 8.2, because Laravel 13 requires PHP 8.3 or newer. It was intentionally not attempted.
- **Blocked by infrastructure:** PostgreSQL integration (server reachable, no isolated credentials) and MySQL integration (no server). No service or container was installed or started.
## Proposed CI matrix

Run these rows on Linux first:

| Laravel | PHP | Dependencies | SQLite | PostgreSQL | MySQL |
|---|---:|---|---|---|---|
| 12 | 8.2 | minimum | required | representative current supported server | representative current supported server |
| 12 | 8.3 | current | required | optional nightly | optional nightly |
| 12 | 8.4 | current | required | required release gate | required release gate |
| 12 | 8.5 | current | required | optional nightly | optional nightly |
| 13 | 8.3 | minimum | required | representative current supported server | representative current supported server |
| 13 | 8.4 | current | required | required release gate | required release gate |
| 13 | 8.5 | current | required | optional nightly | optional nightly |

“Representative current supported server” must be replaced by exact pinned service versions in the workflow and release notes. Add Windows only if the package promises Windows-specific support; database semantics matter more than runner diversity for this package.

Each row should run Composer validation, dependency resolution verification, PHPUnit, Larastan/PHPStan, PHPCS, and `composer audit`. Minimum jobs use `composer update --prefer-lowest --prefer-stable`; current jobs use a clean update. Cache downloads, never `vendor/` results across incompatible rows.

## Claim policy

A version is “supported” only after a green CI row and an explicit support window. A database is “verified” only after its integration suite runs against an identified server version. Documentation should separate framework support from database verification and from the independent demo coverage.


## Prepared GitHub Actions matrix

`compatibility.yml` defines the five locally verified rows as mandatory and adds
PHP 8.3/Laravel 12-13 plus PHP 8.5/Laravel 12-13 as experimental rows. Experimental
jobs use `continue-on-error` and do not establish support until GitHub runs them
successfully and the owner promotes them. `quality.yml` runs metadata validation,
dependency audit, PHPUnit, Larastan/PHPStan and PHPCS on PHP 8.4/Laravel 12.

No workflow has run because no Git repository or GitHub remote exists. PostgreSQL
and MySQL services are intentionally absent: the current tests configure SQLite,
so merely starting database containers would not verify those engines.
