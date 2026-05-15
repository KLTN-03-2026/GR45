<?php

namespace App\Services\AiAgent\Modules\Chat\Dto;

/**
 * Đầu ra LLM Preprocess — intent + entity + dữ liệu đã chuẩn hóa (ngày/tỉnh sau bước sau).
 *
 * @phpstan-type EntityMap array<string, mixed>
 * @phpstan-type NormalizedMap array<string, mixed>
 */
final readonly class PreprocessResult
{
    /**
     * @param  EntityMap  $entities
     * @param  NormalizedMap  $normalized
     */
    public function __construct(
        public string $intent,
        public array $entities,
        public array $normalized,
    ) {}

    /**
     * @param  NormalizedMap  $patch
     */
    public function withNormalized(array $patch): self
    {
        return new self($this->intent, $this->entities, array_merge($this->normalized, $patch));
    }

    /**
     * @param  EntityMap  $entities
     */
    public function withEntities(array $entities): self
    {
        return new self($this->intent, $entities, $this->normalized);
    }
}
