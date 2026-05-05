<?php

namespace App\Services\AiAgent\Modules\Chat\Dto;

/**
 * Kết quả Tool Service (diagram: TOOL → execute → result).
 */
final readonly class ToolRunResult
{
    public function __construct(
        public bool $ok,
        public string $summaryForUser,
        public array $payload = [],
    ) {}

    /**
     * @param  array<string, mixed>  $row  Từ {@see ToolInterface::execute()}
     */
    public static function fromExecuteArray(array $row): self
    {
        $ok = (bool) ($row['ok'] ?? false);
        $summary = (string) ($row['summary_for_user'] ?? $row['summaryForUser'] ?? '');
        $payload = is_array($row['payload'] ?? null) ? $row['payload'] : [];

        return new self($ok, $summary, $payload);
    }
}
