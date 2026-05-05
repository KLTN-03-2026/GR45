<?php

namespace App\Services\AiAgent\Modules\Chat\Pipeline;

use App\Services\AiAgent\Modules\Chat\Dto\ChatContext;
use App\Services\AiAgent\Modules\Chat\Dto\PreprocessResult;

/**
 * **State Machine** — merge state + entity (diagram TOOL PATH).
 */
final class StateMachine
{
    public function merge(ChatContext $context, PreprocessResult $pre): array
    {
        return array_merge($context->structuredState, [
            'intent' => $pre->intent,
            'entities' => $pre->entities,
            'normalized' => $pre->normalized,
            'khach_hang_id' => $context->khachHangId,
            'geo' => [
                'latitude' => $context->latitude,
                'longitude' => $context->longitude,
            ],
        ]);
    }
}
