<?php

namespace App\Services\AiAgent\Modules\Chat\Pipeline;

use App\Services\AiAgent\Modules\Chat\Dto\PreprocessResult;
use App\Services\AiAgent\Modules\Chat\Dto\QueryPlan;
use App\Services\AiAgent\Modules\Chat\Dto\QueryPlanMode;

/**
 * **Query Planner** — TOOL vs KNOWLEDGE (diagram: plan(intent, entities)).
 */
final class QueryPlanner
{
    public function plan(PreprocessResult $pre): QueryPlan
    {
        return match ($pre->intent) {
            'my_tickets', 'book_ticket', 'trip_search', 'trip_stops', 'trip_seats' => new QueryPlan(QueryPlanMode::Tool),
            default => new QueryPlan(QueryPlanMode::Knowledge),
        };
    }
}
