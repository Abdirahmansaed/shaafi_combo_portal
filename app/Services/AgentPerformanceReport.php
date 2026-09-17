<?php

namespace App\Services;

use App\Models\SubscriberAction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AgentPerformanceReport
{
    /** Shared completed-work query for the Superadmin report and its PDF. */
    public function query(Request $request, Carbon $now): Builder
    {
        $query = SubscriberAction::query()
            ->where('agent_status', 'COMPLETED')
            ->whereNotNull('done_by')
            ->whereNotNull('completed_at');

        [$start, $end] = $this->dates($request, $now);
        if ($start && $end) {
            $query->whereBetween('completed_at', [$start, $end]);
        }

        return $query;
    }

    public function results(Request $request, Carbon $now)
    {
        return $this->query($request, $now)
            ->selectRaw('done_by, COUNT(*) as completed')
            ->groupBy('done_by')
            ->orderByDesc('completed')
            ->with('doneBy:id,firstName,last_name,username')
            ->get();
    }

    public function rangeLabel(Request $request): string
    {
        if ($request->range === 'today') return 'Today';
        if ($request->range === 'week') return 'This Week';
        if ($request->range === 'month') return 'This Month';
        if ($request->range === 'custom' && $request->filled('from') && $request->filled('to')) return $request->from.' to '.$request->to;

        return 'All time';
    }

    private function dates(Request $request, Carbon $now): array
    {
        if ($request->range === 'today') return [$now->copy()->startOfDay(), $now->copy()->endOfDay()];
        if ($request->range === 'week') return [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()];
        if ($request->range === 'month') return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];
        if ($request->range === 'custom' && $request->filled('from') && $request->filled('to')) {
            return [Carbon::parse($request->from)->startOfDay(), Carbon::parse($request->to)->endOfDay()];
        }

        return [null, null];
    }
}
