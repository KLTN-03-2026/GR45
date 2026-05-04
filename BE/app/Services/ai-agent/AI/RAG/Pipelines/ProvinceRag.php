<?php

namespace App\Services\AiAgent\AI\RAG\Pipelines;

use App\Services\AiAgent\Modules\Chat\Dto\ChatContext;
use App\Services\AiAgent\Modules\Chat\Dto\PreprocessResult;

/**
 * Ngữ cảnh tỉnh/thành từ {@see ProvinceResolver} (`province_matches`) đưa vào khối RAG.
 */
final class ProvinceRag
{
    /**
     * @return list<array{text: string}>
     */
    public function fetch(ChatContext $context, PreprocessResult $pre): array
    {
        $matches = $pre->normalized['province_matches'] ?? [];
        if (! is_array($matches) || $matches === []) {
            return [];
        }

        $parts = [];
        foreach ($matches as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['ten_tinh_thanh'] ?? ''));
            if ($name === '') {
                continue;
            }
            $code = trim((string) ($row['ma_tinh_thanh'] ?? ''));
            $parts[] = $code !== '' ? "{$name} (mã: {$code})" : $name;
        }

        if ($parts === []) {
            return [];
        }

        $unique = array_values(array_unique($parts));

        $lines = [
            '### Đơn vị hành chính (chuẩn từ CSDL bảng tinh_thanhs; cùng dữ liệu được embed vào catalog tri thức RAG)',
            'Danh sách dòng dưới là nguồn đúng cho lượt hội thoại này (không cần nhớ hết 63 tỉnh — chỉ xử lý các mục xuất hiện ở đây).',
            'Quy tắc: (1) Khi nhắc tới một đơn vị có trong danh sách, viết đúng nguyên văn tên + mã như dưới — không tự đặt tên hành chính khác (tên gọi dân gian, viết tắt khác, hoặc tên không có trong danh sách).',
            '(2) Tin khách có thể gọi đủ kiểu (Huế, Sài Gòn, HCM, …): trong câu trả lời vẫn map về đúng tên chính thức trong danh sách nếu mục đó liên quan.',
            '(3) Địa phương không nằm trong danh sách thì không suy diễn tên chính thức; có thể hỏi lại hoặc chỉ nói theo ngữ cảnh RAG khác nếu có.',
            'Danh sách: '.implode('; ', $unique).'.',
        ];

        return [['text' => implode("\n", $lines)]];
    }
}
