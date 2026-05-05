<?php

namespace App\Services\AiAgent\Modules\Chat\Pipeline;

use App\Services\AiAgent\Domain\Tools\ToolMissingFieldsChecker;
use App\Services\AiAgent\Domain\Tools\ToolRegistry;
use App\Services\AiAgent\Modules\Chat\Dto\ValidationOutcome;

/**
 * Thiếu field — schema từ {@see ToolInterface::parameters()} hoặc hook {@see ToolMissingFieldsChecker}.
 */
final class Validator
{
    public function __construct(
        private readonly ToolRegistry $registry,
    ) {}

    /**
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $arguments  Đối số sau router (đã gộp raw_message, …)
     */
    public function validate(array $state, string $toolName, array $arguments = []): ValidationOutcome
    {
        if ($toolName === '' || $toolName === 'unknown') {
            return new ValidationOutcome([]);
        }

        $tool = $this->registry->get($toolName);
        if ($tool === null) {
            return new ValidationOutcome([]);
        }

        if ($tool instanceof ToolMissingFieldsChecker) {
            return new ValidationOutcome($tool->missingFields($state, $arguments));
        }

        $schema = $tool->parameters();
        $required = $schema['required'] ?? [];
        if (! is_array($required)) {
            return new ValidationOutcome([]);
        }

        $missing = [];
        foreach ($required as $field) {
            if (! is_string($field)) {
                continue;
            }
            if (! array_key_exists($field, $arguments)) {
                $missing[] = $field;

                continue;
            }
            $v = $arguments[$field];
            if ($v === null || (is_string($v) && trim($v) === '')) {
                $missing[] = $field;
            }
        }

        return new ValidationOutcome($missing);
    }
}
