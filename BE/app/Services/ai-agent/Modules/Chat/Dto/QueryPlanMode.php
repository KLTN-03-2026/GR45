<?php

namespace App\Services\AiAgent\Modules\Chat\Dto;

/**
 * Kết quả {@see \App\Services\AiAgent\Modules\Chat\Pipeline\QueryPlanner} — nhánh TOOL vs KNOWLEDGE trong sequence diagram.
 */
enum QueryPlanMode: string
{
    case Tool = 'TOOL';
    case Knowledge = 'KNOWLEDGE';
}
