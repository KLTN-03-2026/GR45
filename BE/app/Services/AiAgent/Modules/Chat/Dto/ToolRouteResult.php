<?php

namespace App\Services\AiAgent\Modules\Chat\Dto;

/**
 * Kết quả {@see \App\Services\AiAgent\Modules\Chat\Pipeline\ToolRouter} — tên tool + đối số cho validator/executor.
 */
final readonly class ToolRouteResult
{
    /**
     * @param  array<string, mixed>  $arguments
     */
    public function __construct(
        public ?string $toolName,
        public array $arguments = [],
    ) {}
}
