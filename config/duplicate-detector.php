<?php

declare(strict_types=1);

return [
    /*
     * Map each admitted Eloquent model class to its exact/fuzzy engines and
     * optional classification policy. An empty map admits no model.
     * Exact rules default max_candidates to 1000 and should declare it explicitly.
     * See docs/configuration.md for the complete validated schema.
     */
    'models' => [],

    // Retained for backwards-compatible configuration publication; model rules
    // use their explicit exact.batch_size and fuzzy.limits values.
    'batch_size' => 500,
];
