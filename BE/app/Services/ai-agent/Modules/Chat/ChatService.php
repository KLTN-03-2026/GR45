<?php

namespace App\Services\AiAgent\Modules\Chat;

use App\Services\AiAgent\Domain\Tools\ToolExecutor;
use App\Services\AiAgent\Infrastructure\Persistence\ChatMemoryRepository;
use App\Services\AiAgent\Modules\Chat\Dto\ChatContext;
use App\Services\AiAgent\Modules\Chat\Dto\PreprocessResult;
use App\Services\AiAgent\Modules\Chat\Dto\QueryPlanMode;
use App\Services\AiAgent\Modules\Chat\Pipeline\ContextBuilder;
use App\Services\AiAgent\Modules\Chat\Pipeline\Preprocessor;
use App\Services\AiAgent\Modules\Chat\Pipeline\ProvinceResolver;
use App\Services\AiAgent\Modules\Chat\Pipeline\QueryPlanner;
use App\Services\AiAgent\Modules\Chat\Pipeline\RagOrchestrator;
use App\Services\AiAgent\Modules\Chat\Pipeline\ResponseWriter;
use App\Services\AiAgent\Modules\Chat\Pipeline\StateMachine;
use App\Services\AiAgent\Modules\Chat\Pipeline\ToolRouter;
use App\Services\AiAgent\Modules\Chat\Pipeline\Validator;

/**
 * Điều phối pipeline Chat — các bước trong {@see Pipeline}.
 */
final class ChatService
{
    public function __construct(
        private readonly ContextBuilder $contextBuilder,
        private readonly Preprocessor $preprocessor,
        private readonly ProvinceResolver $provinceResolver,
        private readonly QueryPlanner $queryPlanner,
        private readonly StateMachine $stateMachine,
        private readonly ToolRouter $toolRouter,
        private readonly Validator $validator,
        private readonly ToolExecutor $toolExecutor,
        private readonly RagOrchestrator $ragOrchestrator,
        private readonly ResponseWriter $responseWriter,
        private readonly ChatMemoryRepository $memory,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $history
     * @return array{assistant: string, metadata: array<string, mixed>}
     */
    public function run(
        string $message,
        array $history,
        ?string $sessionId,
        ?int $khachHangId,
        mixed $latitude,
        mixed $longitude,
    ): array {
        $steps = ['context_builder'];

        $ctx = $this->contextBuilder->build(
            $message,
            $sessionId,
            $khachHangId,
            $history,
            $latitude,
            $longitude,
        );

        $steps[] = 'llm_preprocess';
        $pre = $this->preprocessor->preprocess($ctx);

        $steps[] = 'province_resolver';
        $pre = $this->provinceResolver->resolve($ctx, $pre);

        $steps[] = 'query_planner';
        $plan = $this->queryPlanner->plan($pre);

        $metaAi = [
            'provider' => 'ollama',
            'model' => config('ai.chat_model'),
        ];

        $pipeline = [
            'intent' => $pre->intent,
            'mode' => $plan->mode->value,
            'steps' => $steps,
        ];

        if ($plan->mode === QueryPlanMode::Tool) {
            return $this->runToolPath($ctx, $pre, $pipeline, $metaAi, $message, $sessionId, $khachHangId);
        }

        return $this->runKnowledgePath($ctx, $pre, $pipeline, $metaAi, $message, $sessionId, $khachHangId);
    }

    /**
     * @param  array<string, mixed>  $pipeline
     * @return array{assistant: string, metadata: array<string, mixed>}
     */
    private function runToolPath(
        ChatContext $ctx,
        PreprocessResult $pre,
        array $pipeline,
        array $metaAi,
        string $originalMessage,
        ?string $sessionId,
        ?int $khachHangId,
    ): array {
        $pipeline['steps'][] = 'state_machine';
        $state = $this->stateMachine->merge($ctx, $pre);

        $pipeline['steps'][] = 'tool_router';
        $route = $this->toolRouter->resolve($pre->intent, $state, $ctx);
        $toolName = ($route->toolName !== null && $route->toolName !== '') ? $route->toolName : 'unknown';
        $pipeline['tool'] = $toolName;
        $pipeline['tool_arguments'] = $route->arguments;

        $pipeline['steps'][] = 'validator';
        $validation = $this->validator->validate($state, $toolName, $route->arguments);

        if ($validation->hasMissing()) {
            $pipeline['steps'][] = 'llm_ask_user';
            $draft = $this->responseWriter->generateClarificationQuestion($validation->missingFields, $ctx);
            $assistant = $this->responseWriter->formatFinal($ctx, $draft);
            $pipeline['steps'][] = 'memory_service';
            $metaClarify = ['ai' => $metaAi, 'pipeline' => $pipeline];
            if ($toolName === 'list_my_tickets' && in_array('đăng_nhập_tài_khoản', $validation->missingFields, true)) {
                $metaClarify['login_required'] = true;
            }
            $this->memory->saveTurn($sessionId, $khachHangId, $originalMessage, $assistant, $metaClarify);

            return [
                'assistant' => $assistant,
                'metadata' => $metaClarify,
            ];
        }

        $pipeline['steps'][] = 'tool_execution';
        $run = $this->toolExecutor->execute($toolName, $state, $khachHangId, $route->arguments);
        $draft = $run->summaryForUser;
        $assistant = $this->responseWriter->formatFinal($ctx, $draft);

        $pipeline['steps'][] = 'memory_service';
        $meta = ['ai' => $metaAi, 'pipeline' => $pipeline, 'tool_payload' => $run->payload];
        if (! $run->ok && ! empty($run->payload['login_required'])) {
            $meta['login_required'] = true;
        }
        $this->memory->saveTurn($sessionId, $khachHangId, $originalMessage, $assistant, $meta);

        return [
            'assistant' => $assistant,
            'metadata' => $meta,
        ];
    }

    /**
     * @param  array<string, mixed>  $pipeline
     * @return array{assistant: string, metadata: array<string, mixed>}
     */
    private function runKnowledgePath(
        ChatContext $ctx,
        PreprocessResult $pre,
        array $pipeline,
        array $metaAi,
        string $originalMessage,
        ?string $sessionId,
        ?int $khachHangId,
    ): array {
        $pipeline['steps'][] = 'rag_orchestrator';
        $rag = $this->ragOrchestrator->retrieve($ctx, $pre);

        $pipeline['steps'][] = 'llm_response';
        $draft = $this->responseWriter->generateKnowledgeAnswer($ctx, $pre, $rag->combinedText());
        $assistant = $this->responseWriter->formatFinal($ctx, $draft);

        $pipeline['steps'][] = 'memory_service';
        $this->memory->saveTurn($sessionId, $khachHangId, $originalMessage, $assistant, [
            'ai' => $metaAi,
            'pipeline' => $pipeline,
            'rag' => [
                'pdf_chunk_count' => count($rag->pdfChunks),
                'memory_chunk_count' => count($rag->memoryChunks),
            ],
        ]);

        return [
            'assistant' => $assistant,
            'metadata' => ['ai' => $metaAi, 'pipeline' => $pipeline],
        ];
    }
}
