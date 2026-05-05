<?php

namespace App\Services\AiAgent\Modules\Chat\Dto;

/**
 * Ngữ cảnh sau bước Context Builder (session + history + geo + state nội bộ).
 */
final readonly class ChatContext
{
    /**
     * @param  array<int, array<string, mixed>>  $history
     * @param  array<string, mixed>  $structuredState  State Machine — merge từ entity / vòng trước.
     */
    public function __construct(
        public string $message,
        public ?string $sessionId,
        public ?int $khachHangId,
        public array $history,
        public ?float $latitude,
        public ?float $longitude,
        public array $structuredState = [],
    ) {}

    public function withState(array $structuredState): self
    {
        return new self(
            $this->message,
            $this->sessionId,
            $this->khachHangId,
            $this->history,
            $this->latitude,
            $this->longitude,
            $structuredState,
        );
    }
}
