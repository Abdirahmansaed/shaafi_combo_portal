<?php

namespace App\Services;

use App\Models\SubscriberAction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgentPerformanceReport
{
    /** Shared completed-work query for the Superadmin report and its PDF. */
    public function query(Request $request, Carbon $now)
    {
        $actions = SubscriberAction::query()
            ->where('agent_status', 'COMPLETED')
            ->whereNotNull('done_by')
            ->whereNotNull('completed_at')
            ->select(['purchase_id as work_id', 'done_by', 'completed_at']);

        // Older Active Subscribers completions predate subscriber_actions.
        // Include only unmatched legacy rows so a synchronized completion is
        // counted once, never twice.
        $legacy = DB::connection('mysql_portal')->table('active_subscribers as active')
            ->where('active.action_status', 'COMPLETED')
            ->whereNotNull('active.done_by')
            ->whereNotNull('active.completed_at')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('subscriber_actions as action')
                    ->whereColumn('action.purchase_id', 'active.business_purchase_id');
            })
            ->select(['active.business_purchase_id as work_id', 'active.done_by', 'active.completed_at']);

        $query = DB::connection('mysql_portal')->query()
            ->fromSub($actions->unionAll($legacy), 'completed_work');

        [$start, $end] = $this->dates($request, $now);
        if ($start && $end) {
            $query->whereBetween('completed_at', [$start, $end]);
        }

        return $query;
    }

    public function results(Request $request, Carbon $now)
    {
        $results = $this->query($request, $now)
            ->selectRaw('done_by, COUNT(*) as completed')
            ->groupBy('done_by')
            ->orderByDesc('completed')
            ->get();

        $users = User::whereIn('id', $results->pluck('done_by'))->get()->keyBy('id');
        return $results->each(function ($result) use ($users) {
            $result->doneBy = $users[$result->done_by] ?? null;
        });
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
