<?php

return [
    /** Base URL Ollama — khớp `AI_OLLAMA_URL` trong `.env`. */
    'base_url' => rtrim((string) env('AI_OLLAMA_URL', 'http://127.0.0.1:11434'), '/'),

    /** Model chat — `AI_OLLAMA_CHAT_MODEL`. */
    'chat_model' => (string) env('AI_OLLAMA_CHAT_MODEL', 'qwen2.5:7b'),

    /** Embedding / rerank — khớp `.env`. */
    'embed_model' => (string) env('AI_OLLAMA_EMBED_MODEL', 'nomic-embed-text'),

    /**
     * Model rerank trên Ollama (pull về máy). Mặc định: tag bạn đang dùng.
     * Nếu server trả 404 cho /api/rerank: Ollama release chính thường chưa có rerank — đặt AI_RERANK_CHAIN=none hoặc dùng TEI (AI_HF_RERANK_URL).
     */
    'rerank_model' => (string) env('AI_OLLAMA_RERANK_MODEL', 'qllama/bge-reranker-v2-m3:latest'),

    /** URL POST rerank đầy đủ (tuỳ chọn). Trống → thử {base_url}/api/rerank rồi /v1/rerank. */
    'ollama_rerank_url' => env('AI_OLLAMA_RERANK_URL') ? rtrim((string) env('AI_OLLAMA_RERANK_URL'), '/') : null,

    /**
     * Chuỗi provider (thứ tự fallback, phân tách dấu phẩy):
     * - `embedding_provider`: ollama, huggingface (HF cần AI_HF_*)
     * - `provider_chain`: LLM chat — ollama, groq (Groq cần AI_GROQ_API_KEY)
     * - `rerank_chain`: ollama, huggingface (HF cần AI_HF_RERANK_URL), none (bỏ qua rerank)
     */
    'embedding_provider' => (string) env('AI_EMBEDDING_PROVIDER', 'ollama,huggingface'),
    'provider_chain' => (string) env('AI_PROVIDER_CHAIN', 'ollama,groq'),
    'rerank_chain' => (string) env('AI_RERANK_CHAIN', 'ollama,huggingface'),

    'groq_api_key' => env('AI_GROQ_API_KEY'),
    'groq_model' => (string) env('AI_GROQ_MODEL', 'llama-3.1-8b-instant'),
    'groq_api_url' => rtrim((string) env('AI_GROQ_API_URL', 'https://api.groq.com/openai/v1'), '/'),
    'groq_timeout' => (int) env('AI_GROQ_TIMEOUT', 120),

    'hf_token' => env('AI_HF_TOKEN'),
    'hf_embed_base_url' => rtrim((string) env('AI_HF_EMBED_BASE_URL', 'https://api-inference.huggingface.co'), '/'),
    'hf_embed_model' => (string) env('AI_HF_EMBED_MODEL', 'sentence-transformers/all-MiniLM-L6-v2'),
    'hf_rerank_url' => (string) env('AI_HF_RERANK_URL', ''),

    /** Timeout HTTP (giây) gọi Ollama chat/stream/embed. */
    'timeout' => (int) env('AI_OLLAMA_TIMEOUT', 600),

    /**
     * Nhận diện intent (Preprocessor): rule đầu khớp thắng. Regex PCRE, test trên tin nhắn đã mb_strtolower.
     * Thêm intent mới: thêm rule + {@see ToolRegistry::bindIntent} + {@see QueryPlanner} (nhánh Tool).
     */
    'intent_default' => 'chat_general',

    'intent_rules' => [
        [
            'intent' => 'my_tickets',
            'patterns' => [
                '/vé\s+của\s+tôi|vé\s+đã\s+đặt|đơn\s+hàng\s+vé|tra\s+cứu\s+vé|xem\s+vé|lịch\s+sử\s+đặt/i',
            ],
        ],
        [
            'intent' => 'book_ticket',
            'patterns' => [
                '/đặt\s+vé|mua\s+vé|book|lấy\s+ghế|giữ\s+chỗ/i',
            ],
        ],
        [
            'intent' => 'trip_search',
            'patterns' => [
                '/tuyến|chuyến\s+xe|giờ\s+chạy|lịch\s+trình|lịch\s+xe|còn\s+chuyến|chuyến\s+nào|tìm\s+(xe|chuyến)|có\s+chuyến|co\s+chuyen\b/iu',
                '/\bxe\s+(đi|đến|tới|từ|chạy|không|nào)\b/u',
                '/\bcó\s+xe|co\s+xe\b/iu',
                '/\b(trip|schedule)\b/i',
                '/\b(huế|hue|hà\s+nội|hanoi|sài\s+gòn|thành\s+phố\s+hồ\s+chí\s+minh|hồ\s+chí\s+minh|hcm|đà\s+nẵng|da\s+nang|nha\s+trang|nhà\s+trang)\s*(đi|đến|tới|về|->|→|-|to)\s*/iu',
                '/\s(đi|đến|tới|về|vào|ra)\s+(đà|hà|huế|nha|hồ|sài|thành|từ|hn|dn|hue|hcm|sg)\b/u',
                '/\b(đi|đến|tới)\s+từ\b/u',
                '/\bfrom\s+.+\bto\b/is',
                '/\bfrom\s+(hanoi|hue|hcm|da\s*nang|nha\s*trang|hn|sg|dn)\b/i',
                '/\b[a-z]{2,18}\s+to\s+[a-z]{2,18}\b/i',
                '/\b(hn|hcm|sg|hue|dn|hanoi|dng)\s*(-|→|->)?\s*(hn|hcm|sg|hue|dn|hanoi|dng)\b/iu',
                '/\s(-{1,2}|→|->)\s*(đà|hà|huế|nha|hue|dn|đi|hn|sg)\b/iu',
                '/\b(khứ\s*hồi|vé\s+xe\s+(đi|từ))\b/iu',
                '/\b(từ|về)\s+(huế|hà\s+nội|hồ\s+chí\s+minh|hcm|hn|đà\s+nẵng|nha\s+trang|hue|hanoi|dn|sg)\b/iu',
                '/\b(từ|về|đi)\s+(hn|dn|hue|hcm|sg)\b/iu',
                '/\b(di|den|toi|tu)\s+(nha|da|hue|hn|dn|hcm|sg)\b/iu',
            ],
        ],
        [
            'intent' => 'trip_stops',
            'patterns' => [
                '/trạm\s*(đón|trả|don|tra)|điểm\s*(đón|trả)|danh\s*sách\s*trạm|(?:id|mã)\s*trạm/i',
                '/(?:trạm|tram).*(?:chuyến|chuyen)\s*\d+|(?:chuyến|chuyen)\s*\d+.*(?:trạm|tram)/iu',
            ],
        ],
        [
            'intent' => 'trip_seats',
            'patterns' => [
                '/ghế\s*trống|ghế\s*còn|sơ\s*đồ\s*ghế|danh\s*sách\s*ghế|(?:mã|ma)\s*ghế|còn\s*ghế|chọn\s*ghế/i',
                '/(?:ghế|ghe).*(?:chuyến|chuyen)\s*\d+|(?:chuyến|chuyen)\s*\d+.*(?:ghế|ghe)/iu',
            ],
        ],
    ],

    /**
     * Đồng bộ toàn bộ `tinh_thanhs` → `ai_chunks` (embedding) khi boot app: xóa catalog cũ + embed lại nếu dữ liệu đổi (fingerprint) hoặc chưa có file chữ ký.
     * Luôn full resync (bỏ fingerprint): chỉnh thành true khi cần ép (vd. đổi model embed).
     * CLI: `php artisan ai:embed-provinces` / `--force`. Catalog luôn gọi LLM 1 lần/tỉnh khi embed; embed nhanh không LLM: `--no-llm`.
     */
    'province_catalog_sync_on_boot' => true,

    'province_catalog_boot_always_full' => false,
];
