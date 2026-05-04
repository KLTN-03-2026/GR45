<?php

namespace App\Services\AiAgent\Modules\Chat\Pipeline;

use App\Services\AiAgent\Modules\Chat\Dto\ChatContext;

/**
 * Sequence: **Context Builder** — gom message + session + history + geo (+ state DB sau này).
 */
final class ContextBuilder
{
    /**
     * @param  array<int, array<string, mixed>>  $history
     */
    public function build(
        string $message,
        ?string $sessionId,
        ?int $khachHangId,
        array $history,
        mixed $latitude,
        mixed $longitude,
        array $structuredState = [],
    ): ChatContext {
        $lat = is_numeric($latitude) ? (float) $latitude : null;
        $lon = is_numeric($longitude) ? (float) $longitude : null;

        return new ChatContext(
            message: trim($message),
            sessionId: $sessionId,
            khachHangId: $khachHangId,
            history: $history,
            latitude: $lat,
            longitude: $lon,
            structuredState: $structuredState,
        );
    }
}
