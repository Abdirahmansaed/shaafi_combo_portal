<?php

namespace App\Http\Controllers;

use App\Services\ComboPurchaseQuery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(ComboPurchaseQuery $purchases)
    {
        $now = now();
        $summary = Cache::remember('reports.valid-purchase-summary.v1', now()->addMinutes(5), function () use ($purchases, $now) {
            return $purchases->selectSummary($purchases->base(), $now)
                ->selectRaw('SUM(price) as revenue')->first();
        });
        $latestId = Cache::remember('reports.latest-id', now()->addHour(), fn () => DB::connection('mysql_business')->table(ComboPurchaseQuery::TABLE)->max('id'));
        $trend = $purchases->base()->where('id', '>=', max(0, $latestId - 500000))
            ->whereBetween('purchase_date', [$now->copy()->subDays(6)->startOfDay(), $now])
            ->selectRaw('DATE(purchase_date) as day, COUNT(*) as total')->groupBy('day')->orderBy('day')->get();
        return view('reports.index', compact('summary', 'trend'));
    }
}
