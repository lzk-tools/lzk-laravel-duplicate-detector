# Contributing

This is an unpublished Phase 2A package with provisional APIs. Licensing and
contribution terms remain pending; review them before external contributions.

## Development

Use PHP 8.2+, mbstring, current Composer 2 and PDO SQLite for integration tests.
Laravel 13 requires PHP 8.3+.

```sh
composer install
composer validate
composer check-platform-reqs
composer check
composer audit
```

composer check runs PHPUnit, Larastan/PHPStan level 8 and PHP_CodeSniffer PSR-12.
Public PHPDoc input types are not assumed trustworthy by PHPStan, so defensive
runtime validation remains analyzed. No error baselines or ignored errors are used.

Provider tests need no database. Integration tests use SQLite :memory: with
fixture-only schema creation and inserts, then enable query_only before detection.
Do not connect to consumer databases or add migrations to run these tests.
SQLite tests must fail visibly when the required driver is missing, not silently skip.

In the recorded WSL environment, PDO SQLite is loaded from a project-local module.
See docs/verification.md for the exact environment variable and command.
This local binary is ignored and is not a distributable dependency.

To select a framework branch without editing public constraints:

```sh
composer update --with orchestra/testbench:^10.0 --with laravel/framework:^12.0
composer check
composer update --with orchestra/testbench:^11.0 --with laravel/framework:^13.0
composer check
```

Use a development checkout. Record actual PHP/SQLite and resolved package versions.
composer.lock is local and ignored for this library; a fresh checkout resolves
dependencies while an existing local lock reproduces its own resolution.

## Design rules

- PSR-4 under Lzk\DuplicateDetector, PSR-12, strict types.
- Keep consumer authorization, tenant filters and enabled global scopes intact.
- No mutation of records, supplied Builders or model values.
- No raw sensitive values in package reports, logs or input validation messages.
- Keep exact comparison, contradictions and later risk policies distinct.
- Never treat missing values as positive evidence or ordinary differences as
  identity contradictions without an explicit rule.
- Canonical blocking columns are an explicit consumer precondition; do not add
  fallback broad scans, table writes or SQL normalization shortcuts.
- Document API changes and their compatibility in docs/decisions.md.
- Add behavioral regression tests for security boundaries, paging and rule semantics.
- Do not claim compatibility or production quality from unexecuted combinations.

Phase 2A is the authorized boundary. Fuzzy comparison, weighted scores, graph
analysis, dashboards, Pro features, publication and commits require separate scope.
