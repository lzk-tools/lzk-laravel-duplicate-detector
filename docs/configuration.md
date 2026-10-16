# Declarative configuration

Configuration is keyed by admitted Eloquent model class. A missing model is
rejected. Each model needs at least one exact or fuzzy engine. Classification is
optional and applies to reports from both engines.

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
                // Compared field => existing canonical database column.
                'blocking' => ['email' => 'canonical_email'],
                'contradiction_fields' => ['external_id'],
                'batch_size' => 200,
            ],
            'fuzzy' => [
                'id' => 'contact.name.fuzzy.v1',
                'fields' => [
                    'display_name' => [
                        'id' => 'contact.name.similarity.v1',
                        'normalizer' => 'text',
                        'metric' => 'normalized_levenshtein',
                        'max_code_points' => 256,
                        'threshold' => 0.85, // illustrative; validate for your data
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
                    'id' => 'contact.name-bucket.v1',
                    'blocks' => [
                        [
                            'source_field' => 'name_bucket',
                            'column' => 'name_bucket',
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
                // First complete match wins.
                'rules' => [
                    [
                        'id' => 'contradiction-review.v1',
                        'label' => 'manual-review',
                        'contradictions' => 'present',
                    ],
                    [
                        'id' => 'exact-email.v1',
                        'label' => 'exact-candidate',
                        'comparison_rule_ids' => ['contact.email.exact.v1'],
                        'matched_fields' => ['email'],
                        'contradictions' => 'none',
                        'minimum_evidence' => 1,
                    ],
                    [
                        'id' => 'similar-name.v1',
                        'label' => 'similar-candidate',
                        'comparison_rule_ids' => ['contact.name.fuzzy.v1'],
                        'minimum_similarities' => ['display_name' => 0.85],
                        'present_fields' => ['display_name'],
                    ],
                ],
            ],
        ],
    ],
    'batch_size' => 500,
];
```

Supported normalizer aliases are `email`, `text`, and `phone`. The supported
metric alias is `normalized_levenshtein`. Custom normalizers, metrics and policy
objects remain available through the advanced object APIs; arbitrary classes are
not instantiated from configuration.

Exact blocking maps a configured match field to an existing canonical database
column. Fuzzy blocking maps source fields to existing columns using equality.
Both are mandatory for their respective engine. The package never falls back to
an unblocked table scan. Incorrect or over-selective block values cause false
negatives; indexes and canonical-column maintenance belong to the application.

Classification rules support `comparison_rule_ids`, `matched_fields`,
`minimum_similarities`, `contradictions` (`any`, `none`, `present`),
`minimum_evidence`, `required_evidence_codes`, `present_fields`, and
`missing_fields`. All configured conditions are required.

The resolver validates types, aliases, thresholds, limits and rule invariants
when `DuplicateDetector::for()` is called. This compilation performs no query.
Schema-column validation remains in the candidate finders and therefore happens
when detection is consumed.


## Exact limits

Each exact section accepts `max_candidates` as a positive integer independent
from `batch_size`:

```php
'exact' => [
    // rule, fields, matches and blocking...
    'batch_size' => 200,
    'max_candidates' => 1000,
],
```

When absent, `max_candidates` defaults to the finite value `1000`. Zero,
negative, null, float and string values are rejected before queries. Every
compared candidate consumes the limit, including non-matches.

At the ceiling the terminal exact report has `searchComplete === false`,
`candidatesExamined` equal to the limit, and `incompleteReason` equal to
`max_candidates_reached`. Exactly reaching the limit is conservatively partial
because no probe query is issued. Unexamined candidates can cause false
negatives, so consumers must not present partial output as exhaustive.
