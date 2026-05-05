<?php

namespace App\Services\AiAgent\Modules\Chat\Dto;

/**
 * Validator — thiếu field cho tool (diagram: missing_fields?).
 */
final readonly class ValidationOutcome
{
    /**
     * @param  list<string>  $missingFields
     */
    public function __construct(
        public array $missingFields = [],
    ) {}

    public function hasMissing(): bool
    {
        return $this->missingFields !== [];
    }
}
