<?php

namespace App\Services\AiAgent\Modules\Chat\Pipeline;

use App\Services\AiAgent\Modules\Chat\Dto\ChatContext;
use App\Services\AiAgent\Modules\Chat\Dto\PreprocessResult;
use Illuminate\Support\Facades\Log;

/**
 * Gán intent từ {@see config('ai.intent_rules')} (regex theo thứ tự); mở rộng sau: LLM/NER gọi ở đây.
 */
final class Preprocessor
{
    public function preprocess(ChatContext $context): PreprocessResult
    {
        $m = mb_strtolower($context->message);

        $default = (string) config('ai.intent_default', 'chat_general');
        $intent = $default;

        foreach ((array) config('ai.intent_rules', []) as $rule) {
            if (! is_array($rule) || ! isset($rule['intent'])) {
                continue;
            }
            $name = (string) $rule['intent'];
            foreach ((array) ($rule['patterns'] ?? []) as $pattern) {
                $p = trim((string) $pattern);
                if ($p === '') {
                    continue;
                }
                $matched = @preg_match($p, $m);
                if ($matched === false) {
                    Log::warning('preprocessor.invalid_intent_regex', ['pattern' => $p]);

                    continue;
                }
                if ($matched === 1) {
                    $intent = $name;
                    break 2;
                }
            }
        }

        $entities = [
            'raw_message' => $context->message,
            'has_geo' => $context->latitude !== null && $context->longitude !== null,
        ];

        $normalized = [
            'intent_hint' => $intent,
        ];

        return new PreprocessResult($intent, $entities, $normalized);
    }
}
