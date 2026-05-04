<?php

namespace App\Services\AiAgent\Domain\Tools;

/**
 * Đăng ký tool plug-in — thêm tool mới: bind trong {@see AppServiceProvider}, không sửa pipeline.
 */
final class ToolRegistry
{
    /** @var array<string, ToolInterface> */
    private array $tools = [];

    /** @var array<string, string> intent preprocessor → tên tool đã {@see register()} */
    private array $intentToTool = [];

    public function register(ToolInterface $tool): void
    {
        $this->tools[$tool->name()] = $tool;
    }

    /**
     * Gắn intent (từ Preprocessor) với tool — thêm tool mới: register + bindIntent trong provider, không sửa ToolRouter.
     */
    public function bindIntent(string $intent, string $toolName): void
    {
        if ($this->get($toolName) === null) {
            throw new \InvalidArgumentException("Tool \"{$toolName}\" chưa được register.");
        }
        $this->intentToTool[$intent] = $toolName;
    }

    public function resolveIntentTool(string $intent): ?string
    {
        return $this->intentToTool[$intent] ?? null;
    }

    public function get(string $name): ?ToolInterface
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * @return list<ToolInterface>
     */
    public function all(): array
    {
        return array_values($this->tools);
    }

    /**
     * Manifest cho LLM router (tên + mô tả + schema).
     *
     * @return list<array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public function definitionsForLlm(): array
    {
        $out = [];
        foreach ($this->tools as $tool) {
            $out[] = [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'parameters' => $tool->parameters(),
            ];
        }

        return $out;
    }
}
