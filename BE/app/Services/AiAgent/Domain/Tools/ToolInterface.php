<?php

namespace App\Services\AiAgent\Domain\Tools;

/**
 * Một tool đăng ký — LLM/manifest dùng {@see description()} + {@see parameters()}, runtime gọi {@see execute()}.
 *
 * @phpstan-type JsonSchema array<string, mixed>
 */
interface ToolInterface
{
    public function name(): string;

    public function description(): string;

    /**
     * JSON Schema kiểu function-calling (object + properties + required).
     *
     * @return JsonSchema
     */
    public function parameters(): array;

    /**
     * @param  array<string, mixed>  $input  Tham số đã validate + khóa nội bộ `_state`, `_khach_hang_id` nếu cần.
     * @return array{ok: bool, summary_for_user: string, payload?: array<string, mixed>}
     */
    public function execute(array $input): array;
}
