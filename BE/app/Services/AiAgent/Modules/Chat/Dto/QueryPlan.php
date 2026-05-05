<?php

namespace App\Services\AiAgent\Modules\Chat\Dto;

/**
 * Query Planner — chỉ chọn nhánh; tên tool do {@see \App\Services\AiAgent\Modules\Chat\Pipeline\ToolRouter} quyết định.
 */
final readonly class QueryPlan
{
    public function __construct(
        public QueryPlanMode $mode,
    ) {}
}
