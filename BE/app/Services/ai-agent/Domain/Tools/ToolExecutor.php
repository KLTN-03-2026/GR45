<?php

namespace App\Services\AiAgent\Domain\Tools;

use App\Services\AiAgent\Modules\Chat\Dto\ToolRunResult;

/**
 * Gọi tool đã đăng ký trong {@see ToolRegistry}.
 */
final class ToolExecutor
{
    public function __construct(
        private readonly ToolRegistry $registry,
    ) {}

    /**
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $arguments
     */
    public function execute(string $toolName, array $state, ?int $khachHangId, array $arguments = []): ToolRunResult
    {
        if ($toolName === '' || $toolName === 'unknown') {
            return new ToolRunResult(
                ok: false,
                summaryForUser: 'Không xác định được thao tác. Bạn thử diễn đạt rõ hơn nhé.',
                payload: [],
            );
        }

        $tool = $this->registry->get($toolName);
        if ($tool === null) {
            return new ToolRunResult(
                ok: false,
                summaryForUser: 'Tool chưa được đăng ký hoặc không khả dụng.',
                payload: ['tool' => $toolName],
            );
        }

        $input = array_merge($arguments, [
            '_state' => $state,
            '_khach_hang_id' => $khachHangId,
        ]);

        try {
            $row = $tool->execute($input);
        } catch (\Throwable $e) {
            return new ToolRunResult(
                ok: false,
                summaryForUser: 'Thực hiện thao tác gặp lỗi tạm thời. Thử lại sau nhé.',
                payload: ['exception' => $e->getMessage()],
            );
        }

        return ToolRunResult::fromExecuteArray(is_array($row) ? $row : []);
    }
}
