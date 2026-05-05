<?php

namespace App\Services\AiAgent\Domain\Conversation;

/**
 * Value object — khóa phiên chat phía client (tối đa 64 ký tự).
 */
final readonly class ChatSessionKey
{
    public function __construct(
        public string $value,
    ) {}
}
