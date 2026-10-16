<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\DTO;

use InvalidArgumentException;

final readonly class DuplicateReport
{
    public const MAX_CANDIDATES_REACHED = 'max_candidates_reached';

    /**
     * One bounded batch of results, not necessarily a complete database scan.
     *
     * Completion metadata is present only on the terminal exact report. A null
     * searchComplete means that the report is not a terminal exact summary or was
     * produced by an engine that does not expose this metadata.
     *
     * @param list<DuplicateMatch> $matches
     */
    public function __construct(
        public array $matches = [],
        public ?bool $searchComplete = null,
        public ?int $candidatesExamined = null,
        public ?string $incompleteReason = null,
    ) {
        if ($candidatesExamined !== null && $candidatesExamined < 0) {
            throw new InvalidArgumentException('Candidates examined cannot be negative.');
        }
        if ($searchComplete === true && $incompleteReason !== null) {
            throw new InvalidArgumentException('A complete search cannot have an incomplete reason.');
        }
        if ($searchComplete === false && $incompleteReason === null) {
            throw new InvalidArgumentException('An incomplete search requires a reason.');
        }
        if ($searchComplete !== null && $candidatesExamined === null) {
            throw new InvalidArgumentException('Search completion metadata requires a candidate count.');
        }
    }
}
