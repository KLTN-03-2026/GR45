<?php

namespace App\Services\AiAgent\Modules\Chat\Pipeline;

use App\Services\AiAgent\AI\LLM\LlmManager;
use App\Services\AiAgent\Modules\Chat\Dto\ChatContext;
use App\Services\AiAgent\Modules\Chat\Dto\PreprocessResult;
use Illuminate\Support\Facades\Log;

/**
 * **Entity Extractor** — Sử dụng LLM để bóc tách thực thể (NER).
 */
final class EntityExtractor
{
    public function __construct(
        private readonly LlmManager $llmManager
    ) {}

    public function extract(ChatContext $context, PreprocessResult $pre): PreprocessResult
    {
        // Chỉ bóc tách cho một số intent nhất định để tiết kiệm tài nguyên
        if (!in_array($pre->intent, ['trip_search', 'book_ticket', 'trip_info'])) {
            return $pre;
        }

        $systemPrompt = <<<PROMPT
Bạn là một trợ lý trích xuất dữ liệu từ tin nhắn của khách hàng đặt vé xe.
Hãy trích xuất các thông tin sau dưới dạng JSON:
- diem_di: Điểm xuất phát (Ví dụ: "Hà Nội")
- diem_den: Điểm đến (Ví dụ: "Hải Phòng")
- ngay_di: Ngày khởi hành (Định dạng: YYYY-MM-DD)
- id_chuyen_xe: Mã số chuyến xe (Dạng số, ví dụ: 15)
- danh_sach_ghe: Mảng các mã ghế (Ví dụ: ["A1", "A2"])

Quy tắc:
1. Chỉ trả về duy nhất chuỗi JSON. Không giải thích gì thêm.
2. Nếu không có thông tin nào, hãy để giá trị là null hoặc mảng rỗng.
3. Ngày hiện tại là: " . now()->toDateString() . ".
4. Nếu người dùng nói \"mai\", \"mốt\", \"hôm nay\", hãy quy đổi sang định dạng YYYY-MM-DD.

Tin nhắn: \"{$context->message}\"
PROMPT;

        try {
            $response = $this->llmManager->chatComplete([
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $context->message]
            ]);

            $json = $this->cleanJson($response);
            $data = json_decode($json, true);

            if (is_array($data)) {
                // Gộp vào entities của PreprocessResult
                $newEntities = array_merge($pre->entities, $data);
                return $pre->withEntities($newEntities);
            }
        } catch (\Throwable $e) {
            Log::warning('EntityExtractor failed: ' . $e->getMessage());
        }

        return $pre;
    }

    private function cleanJson(string $text): string
    {
        if (preg_match('/\{.*\}/s', $text, $matches)) {
            return $matches[0];
        }
        return $text;
    }
}
