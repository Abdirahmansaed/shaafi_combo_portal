<?php

namespace App\Http\Controllers;

use App\Services\ComboPurchaseQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    private const TABLE = 'data_purchase_09_2026';

    private const PRODUCTS = [40720 => 'Daily', 40721 => 'Weekly', 40722 => 'Monthly'];
    private const PRODUCT_IDS = ['40720', '40721', '40722'];

    public function index(ComboPurchaseQuery $comboPurchases)
    {
        // Exact aggregate KPIs must scan this unindexed 36M-row source table on
        // a cache miss. Permit that one database-side calculation to complete;
        // no records are loaded into PHP memory.
        set_time_limit(0);
        $now = now();
        $purchases = $comboPurchases->base();

        // The source table has no product/date indexes. Calculate all cards in
        // one database pass and cache the result briefly to avoid repeat scans.
        $summary = Cache::remember('dashboard.combo-summary.non-null-expiry.v6', now()->addHour(), function () use ($comboPurchases, $now) {
            $summary = $comboPurchases->selectSummary($comboPurchases->base(), $now, true)->first();
            $summary->baseline_id = DB::connection('mysql_business')->table(ComboPurchaseQuery::TABLE)->max('id');
            return $summary;
        });
        $stats = $this->statsFromSummary($summary);
        $packageCounts = collect(self::PRODUCTS)->mapWithKeys(fn ($package, $productId) => [$package => $stats[$package]]);

        // `id` is the only date-adjacent indexed column in the supplied table.
        // The latest 500k imported records cover the newest purchases while
        // keeping the chart and recent-list work within an indexed ID range.
        $latestId = Cache::remember('dashboard.latest-purchase-id.v5', now()->addHour(), fn () => DB::connection('mysql_business')->table(self::TABLE)->max('id'));
        $recentIdFloor = max(0, $latestId - 500000);
        $startDate = $now->copy()->startOfDay()->subDays(6);
        $dailyCounts = (clone $purchases)->where('id', '>=', $recentIdFloor)->whereBetween('purchase_date', [$startDate, $now])->selectRaw('DATE(purchase_date) as purchase_day, COUNT(*) as total')->groupBy('purchase_day')->pluck('total', 'purchase_day');
        $purchaseTrend = collect(range(0, 6))->map(function ($daysAgo) use ($now, $dailyCounts) {
            $date = $now->copy()->startOfDay()->subDays(6 - $daysAgo);
            return ['label' => $date->format('D'), 'total' => (int) ($dailyCounts[$date->toDateString()] ?? 0)];
        });
        $recentPurchases = $comboPurchases->selectPurchase((clone $purchases)->where('id', '>=', $recentIdFloor), $now)
            ->orderByDesc('purchase_date')
            ->simplePaginate(10);

        return view('dashboard.index', compact('stats', 'packageCounts', 'purchaseTrend', 'recentPurchases'));
    }

    /**
     * One-second dashboard polling endpoint. It only reads a tiny, indexed
     * newest-ID range; all-time counters come from the existing cached summary.
     */
    public function live(ComboPurchaseQuery $comboPurchases)
    {
        return response()->json(Cache::remember('dashboard.live-payload.v2', now()->addSecond(), function () use ($comboPurchases) {
            $now = now();
            $summary = Cache::get('dashboard.combo-summary.non-null-expiry.v6');
            $latestId = DB::connection('mysql_business')->table(ComboPurchaseQuery::TABLE)->max('id');
            $recent = $comboPurchases->selectPurchase(
                $comboPurchases->base()->where('id', '>=', max(0, $latestId - 10000)),
                $now
            )->orderByDesc('id')->limit(10)->get();

            $stats = $this->statsFromSummary($summary);
            if ($summary && $latestId > (int) ($summary->baseline_id ?? $latestId)) {
                // Only rows inserted after the cached all-time snapshot are
                // counted here; this is a bounded ID range, not a table scan.
                $delta = $comboPurchases->selectSummary(
                    $comboPurchases->base()->whereBetween('id', [(int) $summary->baseline_id + 1, $latestId]),
                    $now,
                    true
                )->first();
                foreach (['total', 'daily', 'weekly', 'monthly', 'active', 'expired', 'today'] as $key) {
                    $label = $key === 'total' ? 'Total Purchases' : ($key === 'today' ? "Today's Purchases" : ucfirst($key));
                    $stats[$label] += (int) $delta->{$key};
                }
            }

            return [
                'stats' => $stats,
                'recent_purchases' => $recent->map(function ($purchase) {
                    return [
                        'id' => $purchase->id, 'msisdn' => $purchase->msisdn,
                        'package' => $purchase->package,
                        'purchase_date' => \Carbon\Carbon::parse($purchase->purchase_date)->format('d M Y, H:i'),
                        'expiry_date' => \Carbon\Carbon::parse($purchase->expiry_date)->format('d M Y, H:i'),
                        'price' => $purchase->price,
                        'amount_mb' => $purchase->amount_mb, 'status' => $purchase->status,
                    ];
                })->values(),
                'last_updated' => $now->format('d M Y, H:i:s'),
            ];
        }));
    }

    private function statsFromSummary($summary): array
    {
        return [
            'Total Purchases' => (int) optional($summary)->total,
            'Daily' => (int) optional($summary)->daily,
            'Weekly' => (int) optional($summary)->weekly,
            'Monthly' => (int) optional($summary)->monthly,
            'Active' => (int) optional($summary)->active,
            'Expired' => (int) optional($summary)->expired,
            "Today's Purchases" => (int) optional($summary)->today,
        ];
    }
}
