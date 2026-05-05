<?php

namespace App\Services\AiAgent\Modules\Chat\Pipeline;

use App\Services\AiAgent\AI\LLM\LlmManager;
use App\Services\AiAgent\Modules\Chat\Dto\ChatContext;
use App\Services\AiAgent\Modules\Chat\Dto\PreprocessResult;

/**
 * Sinh / định dạng câu trả lời cuối (knowledge, tool draft, hỏi bổ sung).
 */
final class ResponseWriter
{
    public function __construct(
        private readonly LlmManager $llmManager,
    ) {}

    public function generateKnowledgeAnswer(ChatContext $context, PreprocessResult $pre, string $ragCombinedText): string
    {
        $append = $this->buildAppendix($pre, $ragCombinedText);
        $messages = $this->llmManager->driver()->buildMessages($context->message, $context->history, $append);

        return $this->llmManager->driver()->chatComplete($messages);
    }

    /**
     * @param  list<string>  $missingFields
     */
    public function generateClarificationQuestion(array $missingFields, ChatContext $context): string
    {
        $list = implode(', ', $missingFields);
        $system = <<<SYS
Bạn là trợ lý đặt vé xe khách. Khách thiếu thông tin: {$list}.
Hỏi lại NGẮN GỘN (1–2 câu), chỉ tiếng Việt (Latin có dấu), lịch sự, thuần văn bản (không ** markdown). Không xen chữ Trung/Nhật/Hàn. Không liệt kê field dạng kỹ thuật.
SYS;
        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $context->message],
        ];

        return $this->llmManager->driver()->chatComplete($messages);
    }

    public function formatFinal(ChatContext $context, string $draftAnswer): string
    {
        $t = trim($draftAnswer);
        if ($t === '') {
            return $t;
        }
        // LLM đôi khi xen Hán tự / dấu fullwidth CJK; bỏ để giữ câu trả lời tiếng Việt (Latin + dấu).
        $t = preg_replace('/\p{Han}/u', '', $t) ?? $t;
        $t = preg_replace('/[\x{FF01}-\x{FF5E}\x{3000}-\x{303F}]/u', '', $t) ?? $t;
        // Không markdown trong chat (**, __, `…`)
        $t = preg_replace('/\*\*([^*]*)\*\*/u', '$1', $t) ?? $t;
        $t = preg_replace('/\*([^*\n]+)\*/u', '$1', $t) ?? $t;
        $t = preg_replace('/__([^_]+)__/u', '$1', $t) ?? $t;
        $t = preg_replace('/`([^`]+)`/u', '$1', $t) ?? $t;
        $t = str_replace('**', '', $t);
        $t = preg_replace('/\s+/u', ' ', $t) ?? $t;

        return trim($t);
    }

    private function buildAppendix(PreprocessResult $pre, string $ragCombinedText): string
    {
        if ($ragCombinedText === '') {
            return '';
        }

        return "### Bối cảnh tri thức (RAG)\n".$ragCombinedText;
    }
}
