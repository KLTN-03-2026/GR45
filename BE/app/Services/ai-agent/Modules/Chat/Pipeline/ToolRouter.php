<?php

namespace App\Services\AiAgent\Modules\Chat\Pipeline;

use App\Services\AiAgent\Domain\Tools\ToolRegistry;
use App\Services\AiAgent\Modules\Chat\Dto\ChatContext;
use App\Services\AiAgent\Modules\Chat\Dto\ToolRouteResult;

/**
 * Chọn tool theo intent đã map trong {@see ToolRegistry::bindIntent} (không gọi LLM).
 */
final class ToolRouter
{
    public function __construct(
        private readonly ToolRegistry $registry,
    ) {}

    public function resolve(string $intent, array $state, ChatContext $context): ToolRouteResult
    {
        return new ToolRouteResult(
            $this->registry->resolveIntentTool($intent),
            $this->mergeArgsWithState([], $state),
        );
    }

    /**
     * @param  array<string, mixed>  $args
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function mergeArgsWithState(array $args, array $state): array
    {
        $base = [
            'raw_message' => (string) ($state['entities']['raw_message'] ?? ''),
        ];

        return array_merge($base, $args);
    }
}
