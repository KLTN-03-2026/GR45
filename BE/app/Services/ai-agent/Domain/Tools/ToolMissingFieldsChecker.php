<?php

namespace App\Services\AiAgent\Domain\Tools;

/**
 * Khi JSON schema chưa đủ (slot suy ra từ ngôn ngữ tự nhiên), tool tự liệt kê field còn thiếu.
 *
 * @param  array<string, mixed>  $state  State sau {@see \App\Services\AiAgent\Modules\Chat\Pipeline\StateMachine}
 * @param  array<string, mixed>  $arguments  Đối số từ router (LLM / fallback)
 * @return list<string>
 */
interface ToolMissingFieldsChecker
{
    public function missingFields(array $state, array $arguments): array;
}
