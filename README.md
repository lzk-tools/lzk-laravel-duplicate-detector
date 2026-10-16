# LZK Laravel Duplicate Detector

A configurable, explainable, read-only duplicate-candidate detector for Laravel
Eloquent. It provides exact comparison, normalized Levenshtein fuzzy comparison,
explicit contradictions, and deterministic classification. It does not merge,
update, or delete records.

The package is preparing its first public release. The Free package is licensed
under MIT, but it is not currently published on Packagist and has no supported
release yet.

## Requirements

Declared Composer constraints:

- PHP `^8.2`
- Laravel components `illuminate/database ^12.0 || ^13.0`
- Laravel component `illuminate/support ^12.0 || ^13.0`
- PHP extension `mbstring`

Executed compatibility is narrower than the declared range. PHP 8.2 with Laravel
12 and PHP 8.4 with Laravel 12 and 13 passed on SQLite using current and minimum
dependency resolutions. PHP 8.3, PHP 8.5, PostgreSQL, and MySQL remain unverified.
See [compatibility matrix](docs/release/compatibility-matrix.md).

## Installation for local evaluation

Until a public distribution exists, install from a local Composer
path repository in a separate Laravel application:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../lzk-laravel-duplicate-detector",
            "options": {
                "symlink": true
            }
        }
    ]
}
```

```bash
composer require lzk/laravel-duplicate-detector:@dev
php artisan vendor:publish --tag=duplicate-detector-config
```

Laravel package discovery registers
`Lzk\DuplicateDetector\DuplicateDetectorServiceProvider`. Bootstrap merges
configuration and binds services without querying a database.

Configure admitted models in `config/duplicate-detector.php`. The package owns
no tables or migrations.

## Prepare canonical blocking columns

Candidate discovery always requires blocking. The application must maintain the
canonical columns referenced by configuration.

For example, an email block can store the output of `EmailNormalizer` in
`canonical_email`, and a name block can store an application-defined normalized
bucket in `name_block`. Update canonical values whenever source fields change.
Eloquent model events are one option; bulk SQL writes bypass those events and
must apply the same transformation explicitly. Existing rows need a controlled
application migration/backfill before detection is enabled.

Canonical data must be produced deterministically and must match the configured
normalizer. Protect canonical columns as personal data. The package never writes
or repairs them.

For a tenant-scoped model, useful index shapes are commonly:

- `(tenant_id, canonical_email, id)` for exact email blocking;
- `(tenant_id, name_block, id)` for fuzzy name blocking.

Index design is database- and query-specific. Confirm the actual plan with
`EXPLAIN` on PostgreSQL/MySQL/SQLite rather than assuming a universal index.

## Declarative configuration

This complete example uses illustrative thresholds and labels. Validate them
against the consuming domain.

```php
<?php

use App\Models\Contact;

return [
    'models' => [
        Contact::class => [
            'exact' => [
                'id' => 'contact.email.exact.v1',
                'fields' => [
                    'email' => 'email',
                    'external_id' => 'text',
                ],
                'match_fields' => ['email'],
                'blocking' => [
                    'email' => 'canonical_email',
                ],
                'contradiction_fields' => ['external_id'],
                'batch_size' => 100,
                'max_candidates' => 1000,
            ],
            'fuzzy' => [
                'id' => 'contact.name.fuzzy.v1',
                'fields' => [
                    'display_name' => [
                        'id' => 'contact.name.levenshtein.v1',
                        'normalizer' => 'text',
                        'metric' => 'normalized_levenshtein',
                        'max_code_points' => 256,
                        'threshold' => 0.85,
                        'required' => true,
                    ],
                ],
                'contradictions' => [
                    'external_id' => [
                        'id' => 'contact.external-id.different.v1',
                        'normalizer' => 'text',
                    ],
                ],
                'blocking' => [
                    'id' => 'contact.name-block.v1',
                    'blocks' => [
                        [
                            'source_field' => 'name_block',
                            'column' => 'name_block',
                            'normalizer' => 'text',
                        ],
                    ],
                ],
                'limits' => [
                    'max_candidates' => 500,
                    'max_comparisons' => 500,
                    'fetch_batch_size' => 100,
                    'report_batch_size' => 50,
                ],
            ],
            'classification' => [
                'id' => 'contact.review.v1',
                'default_label' => 'not-classified',
                'rules' => [
                    [
                        'id' => 'contact.contradiction-review.v1',
                        'label' => 'manual-review',
                        'contradictions' => 'present',
                    ],
                    [
                        'id' => 'contact.exact-email.v1',
                        'label' => 'exact-candidate',
                        'comparison_rule_ids' => ['contact.email.exact.v1'],
                        'matched_fields' => ['email'],
                        'contradictions' => 'none',
                        'minimum_evidence' => 1,
                    ],
                    [
                        'id' => 'contact.similar-name.v1',
                        'label' => 'similar-candidate',
                        'comparison_rule_ids' => ['contact.name.fuzzy.v1'],
                        'minimum_similarities' => ['display_name' => 0.85],
                        'present_fields' => ['display_name'],
                    ],
                ],
            ],
        ],
    ],
];
```

Exact and fuzzy blocking are mandatory. `max_candidates` is a candidate ceiling;
`batch_size` only controls exact page/report size. Fuzzy candidate and comparison
limits are separate. Classification evaluates rules in declared order and the
first complete rule match wins. Similarity is a metric, not a probability, and
classification never authorizes a merge.

See the [configuration reference](docs/configuration.md) for all keys and
validation behavior.

## Recommended API

```php
<?php

use App\Models\Contact;
use Illuminate\Database\Eloquent\Builder;
use Lzk\DuplicateDetector\Facades\DuplicateDetector;

$contact = Contact::query()
    ->where('tenant_id', $tenantId)
    ->findOrFail($contactId);

$reports = DuplicateDetector::for(Contact::class)
    ->scope(static fn (Builder $query): Builder => $query
        ->where('tenant_id', $tenantId))
    ->detect($contact);

$exactSummary = null;

foreach ($reports as $report) {
    // Do not discard empty reports before reading completion metadata.
    if ($report->searchComplete !== null) {
        $exactSummary = $report;
    }

    foreach ($report->matches as $match) {
        $match->candidateKey;
        $match->comparison->ruleId;
        $match->comparison->matchedFields;
        $match->comparison->fieldComparisons;
        $match->comparison->contradictions;
        $match->classification?->label;
        $match->classification?->reasons;
    }
}

if ($exactSummary?->searchComplete === false) {
    // The exact stage stopped at its candidate ceiling.
    // Do not present the result as exhaustive.
    $exactSummary->candidatesExamined;
    $exactSummary->incompleteReason; // max_candidates_reached
}
```

The equivalent container entry point is
`app(DuplicateDetectorManager::class)->for(Contact::class)`.

## Scope is mandatory

`scope()` is an application authorization boundary. Calling `detect()` without it
throws before querying. The package clones the returned Builder and preserves its
filters, bindings, tenant predicates, and global scopes.

Supplying a callback does not prove authorization. A consumer can still provide
an insufficient tenant predicate or explicitly remove global scopes. Test every
configured model and policy in the consuming application. For high-assurance
deployments, use database credentials restricted to reads and required metadata
queries.

## Partial-result semantics

Completion metadata belongs to the terminal exact report:

- `searchComplete === true`: exact discovery ended before its ceiling for the
  observed iterator.
- `searchComplete === false`: the ceiling was reached; later candidates may have
  been omitted.
- `searchComplete === null`: an intermediate exact report or a report from an
  engine that does not publish this metadata, currently fuzzy.
- `candidatesExamined` counts exact candidates compared, including non-matches.
- `incompleteReason` is `max_candidates_reached` for current partial exact runs.

A truncated exact run with no matches emits an empty terminal report. Reaching
exactly the configured ceiling is conservatively partial because the package
does not issue a probe query.

In a combined pipeline, exact reports are emitted before fuzzy reports. Exact
terminal completeness does not mean the entire pipeline is exhaustive. Fuzzy
limits, blocking false negatives, concurrent writes, and later fuzzy reports are
independent. Preserve empty reports and locate exact terminal metadata using
`searchComplete !== null`.

## Result and privacy semantics

Built-in engines report model class, source/candidate identifiers, field names,
rule IDs, reason/evidence codes, outcomes, thresholds, and similarity values.
They do not place compared raw or normalized field values in results. Identifiers
and metadata can still be sensitive and require output authorization.

A difference is not automatically a contradiction. Contradictions require
explicit configuration. Classification is deterministic policy output, not a
probability or identity decision.

## Known limitations

- Blocking improves bounded execution but can exclude true duplicates.
- Partial exact results can omit later matches.
- Keyset pagination does not create a database snapshot under concurrent writes.
- String/UUID ordering follows database collation.
- Composite primary keys and schema-qualified table names are unsupported.
- Normalized Levenshtein is quadratic in input lengths; configured length and
  comparison limits are resource controls.
- PostgreSQL and MySQL have not been executed in the recorded verification.
- Built-in code performs reads/schema inspection; custom callbacks and extensions
  remain consumer code and can have side effects.
- No merge, write workflow, dashboard, or automatic identity decision exists.
- License, public distribution channel, and private security contact remain
  undecided.

## Public and advanced APIs

The intended `0.1.0` boundary is defined in
[Public API and stability](docs/public-api.md). The facade, manager, configured
detector, documented configuration, returned DTOs/enums, and partial-result
metadata form the recommended API. Concrete engines, finders, rules,
normalizers, metrics, configuration resolvers, and contracts remain advanced and
provisional during `0.x`.

Additional references:

- [Installation](docs/installation.md)
- [Architecture](docs/architecture.md)
- [Decisions](docs/decisions.md)
- [Verification](docs/verification.md)
- [Security policy](SECURITY.md)
- [Release readiness](docs/release/release-readiness.md)
- [Performance plan](docs/release/performance-plan.md)

## Development

```bash
composer install
PHP_INI_SCAN_DIR=:$PWD/.tools/sqlite/conf.d php vendor/bin/phpunit
php vendor/bin/phpstan analyse --memory-limit=512M
php vendor/bin/phpcs
```

The `PHP_INI_SCAN_DIR` prefix is specific to this workspace's SQLite extension.
Use the normal PHP configuration in other environments.


## License

The Free package is licensed under the [MIT License](LICENSE), copyright 2026 LZK Tools. A future Pro package will be separate and its commercial terms remain undecided.
