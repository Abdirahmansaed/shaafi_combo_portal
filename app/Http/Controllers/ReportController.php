<?php

namespace App\Http\Controllers;

use App\Services\ComboPurchaseQuery;
use App\Services\AgentPerformanceReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ReportController extends Controller
{
    public function index(Request $request, ComboPurchaseQuery $purchases, AgentPerformanceReport $agentReport)
    {
        $this->validateAgentFilters($request);
        $now = now();
        $summary = Cache::remember('reports.valid-purchase-summary.v2', now()->addMinutes(5), function () use ($purchases, $now) {
            return $purchases->selectSummary($purchases->base(), $now)
                ->selectRaw('SUM(price) as revenue')->first();
        });
        $latestId = Cache::remember('reports.latest-id.'.config('database.live_purchase_table'), now()->addHour(), fn () => $purchases->latestId());
        $trend = $purchases->base()->where('id', '>=', max(0, $latestId - 500000))
            ->whereBetween('purchase_date', [$now->copy()->subDays(6)->startOfDay(), $now])
            ->selectRaw('DATE(purchase_date) as day, COUNT(*) as total')->groupBy('day')->orderBy('day')->get();
        $agentPerformance = $agentReport->results($request, $now);
        $agentRangeLabel = $agentReport->rangeLabel($request);
        return view('reports.index', compact('summary', 'trend', 'agentPerformance', 'agentRangeLabel'));
    }

    public function exportAgentPerformancePdf(Request $request, AgentPerformanceReport $agentReport)
    {
        $this->validateAgentFilters($request);
        $now = now();

        try {
            return Pdf::loadView('pdf.agent-performance', [
                'agents' => $agentReport->results($request, $now),
                'rangeLabel' => $agentReport->rangeLabel($request),
                'generatedAt' => $now,
            ])->setPaper('a4')->download('agent-performance-report-'.$now->format('Y-m-d').'.pdf');
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withInput()->with('error', 'The Agent Performance PDF could not be generated. Please try again.');
        }
    }

    private function validateAgentFilters(Request $request): void
    {
        $request->validate([
            'range' => 'nullable|in:today,week,month,custom',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);
    }
}
