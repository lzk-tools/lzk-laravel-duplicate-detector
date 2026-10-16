<?php

declare(strict_types=1);

namespace Lzk\DuplicateDetector\DTO;

use InvalidArgumentException;

final readonly class RiskClassification
{
    /**
     * Labels and reason codes belong to an explicit policy; no default thresholds are defined.
     * Evidence must contain safe codes and never original or normalized personal values.
     *
     * @param list<string> $reasons
     * @param list<string> $evidence
     */
    public function __construct(
        public string $label,
        public array $reasons = [],
        public ?string $policyId = null,
        public ?string $ruleId = null,
        public array $evidence = [],
    ) {
        if (trim($label) === '') {
            throw new InvalidArgumentException('A classification label must not be empty.');
        }
        if ($policyId !== null && trim($policyId) === '') {
            throw new InvalidArgumentException('A classification policy identifier must not be empty.');
        }
        if ($ruleId !== null && ($policyId === null || trim($ruleId) === '')) {
            throw new InvalidArgumentException('A classification rule requires non-empty policy and rule identifiers.');
        }
        $this->assertCodes($reasons, 'Classification reasons');
        $this->assertCodes($evidence, 'Classification evidence');
    }

    /** @param list<string> $codes */
    private function assertCodes(array $codes, string $description): void
    {
        if (!array_is_list($codes) || count($codes) !== count(array_unique($codes))) {
            throw new InvalidArgumentException($description . ' must be a list of unique codes.');
        }
        foreach ($codes as $code) {
            if (trim($code) === '') {
                throw new InvalidArgumentException($description . ' must not contain empty codes.');
            }
        }
    }
}
