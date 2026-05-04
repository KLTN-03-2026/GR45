<?php

namespace App\Services\AiAgent\AI\LLM;

use App\Services\AiAgent\AI\Contracts\LLMInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use JsonException;

final class OllamaLLM implements LLMInterface
{
    public function defaultSystemPrompt(): string
    {
        return <<<'TXT'
Bạn là trợ lý hỗ trợ khách đặt vé xe khách liên tỉnh (Việt Nam). Trả lời ngắn gọn, lịch sự, thuần văn bản (không markdown: không dùng ** hoặc # để in đậm/tiêu đề).
Chỉ dùng tiếng Việt (chữ Latin có dấu). Tuyệt đối không xen chữ Trung, Nhật, Hàn hay đoạn tiếng nước ngoài khác.
Nếu trong bối cảnh RAG có khối «Đơn vị hành chính» kèm danh sách tên + mã: đó là chuẩn từ CSDL cho lượt này — khi nhắc các đơn vị đó chỉ dùng đúng nguyên văn trong danh sách (khách gọi tên khác thì bạn vẫn trả lời bằng tên chính thức trong danh sách). Không bịa thêm tên hành chính không có trong danh sách đó.
Nếu thiếu thông tin (điểm đi, điểm đến, ngày giờ), hãy hỏi lại.
Không bịa giá vé hay lịch chạy cụ thể nếu không có trong ngữ cảnh.
TXT;
    }

    public function chatComplete(array $messages): string
    {
        $url = config('ai.base_url').'/api/chat';
        $model = config('ai.chat_model');
        $timeout = config('ai.timeout', 600);

        $client = new Client([
            'timeout' => $timeout,
            'connect_timeout' => 15,
        ]);

        try {
            $res = $client->post($url, [
                'json' => [
                    'model' => $model,
                    'messages' => $messages,
                    'stream' => false,
                    'options' => [
                        'temperature' => 0.35,
                        'top_p' => 0.9,
                    ],
                ],
                'headers' => ['Accept' => 'application/json'],
            ]);
        } catch (GuzzleException $e) {
            Log::warning('ollama.chat_complete_failed', ['e' => $e->getMessage()]);
            throw new \RuntimeException($this->friendlyHttpError($e), 0, $e);
        }

        $raw = (string) $res->getBody();
        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new \RuntimeException('Ollama trả JSON không hợp lệ.');
        }

        return trim((string) ($data['message']['content'] ?? ''));
    }

    public function buildMessages(string $userMessage, array $history, string $systemAppendix = ''): array
    {
        $system = $this->defaultSystemPrompt();
        if ($systemAppendix !== '') {
            $system .= "\n\n".$systemAppendix;
        }
        $out = [
            ['role' => 'system', 'content' => $system],
        ];
        foreach ($history as $row) {
            $role = strtolower(trim((string) ($row['role'] ?? '')));
            $content = trim((string) ($row['content'] ?? ''));
            if ($content === '' || ! in_array($role, ['user', 'assistant'], true)) {
                continue;
            }
            $out[] = ['role' => $role, 'content' => $content];
        }
        $out[] = ['role' => 'user', 'content' => trim($userMessage)];

        return $out;
    }

    private function friendlyHttpError(GuzzleException $e): string
    {
        $msg = $e->getMessage();
        if (str_contains($msg, 'Connection refused') || str_contains($msg, 'Could not resolve host')) {
            return 'Không kết nối được Ollama. Chạy `ollama serve` và kiểm tra AI_OLLAMA_URL trong .env.';
        }

        return 'Lỗi gọi Ollama: '.$msg;
    }
}
