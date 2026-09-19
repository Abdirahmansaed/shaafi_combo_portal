<?php

namespace App\Http\Controllers;

use App\Services\ComboPurchaseQuery;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    private const PRODUCTS = [40720 => 'Daily', 40721 => 'Weekly', 40722 => 'Monthly'];

    public function index(Request $request, ComboPurchaseQuery $comboPurchases)
    {
        set_time_limit(0);
        $now = now();
        [$fromDate, $toDate] = $this->selectedRange($request);
        $purchases = $this->purchasesForRange($comboPurchases, $fromDate, $toDate);
        $stats = $this->liveStats($comboPurchases, $now, $fromDate, $toDate);
        $packageCounts = collect(self::PRODUCTS)->mapWithKeys(fn ($package, $productId) => [$package => $stats[$package.' Purchases']]);

        $purchaseTrend = $this->purchaseTrend($purchases, $now, $fromDate, $toDate);
        $recentPurchases = $comboPurchases->selectPurchase((clone $purchases), $now)
            ->orderByDesc('id')
            ->simplePaginate(10)->appends($request->only('from', 'to'));

        return view('dashboard.index', compact('stats', 'packageCounts', 'purchaseTrend', 'recentPurchases', 'fromDate', 'toDate'));
    }

    /** One-second dashboard polling endpoint; every request reads live business data. */
    public function live(Request $request, ComboPurchaseQuery $comboPurchases)
    {
        $now = now();
        [$fromDate, $toDate] = $this->selectedRange($request);
        $purchases = $this->purchasesForRange($comboPurchases, $fromDate, $toDate);
        $recent = $comboPurchases->selectPurchase((clone $purchases), $now)
            ->orderByDesc('id')->limit(10)->get();
        $stats = $this->liveStats($comboPurchases, $now, $fromDate, $toDate);

        return response()->json([
            'stats' => $stats,
            'package_counts' => collect(self::PRODUCTS)->mapWithKeys(fn ($package, $productId) => [$package => $stats[$package.' Purchases']]),
            'purchase_trend' => $this->purchaseTrend($purchases, $now, $fromDate, $toDate),
            'recent_purchases' => $recent->map(function ($purchase) {
                    return [
                        'id' => (int) $purchase->id, 'msisdn' => $purchase->msisdn,
                        'package' => $purchase->package,
                        'purchase_date' => \Carbon\Carbon::parse($purchase->purchase_date)->format('d M Y, H:i'),
                        'expiry_date' => \Carbon\Carbon::parse($purchase->expiry_date)->format('d M Y, H:i'),
                        'price' => $purchase->price,
                        'amount_mb' => $purchase->amount_mb, 'status' => $purchase->status,
                    ];
            })->values(),
            'last_updated' => $now->format('d M Y, H:i:s'),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    private function liveStats(ComboPurchaseQuery $comboPurchases, $now, ?Carbon $fromDate = null, ?Carbon $toDate = null): array
    {
        $purchases = $comboPurchases->selectSummary($this->purchasesForRange($comboPurchases, $fromDate, $toDate), $now, true)->first();
        $subscribers = $comboPurchases->selectSubscriberSummary($now, $fromDate, $toDate)->first();

        return [
            'Total Subscribers' => (int) optional($subscribers)->total_subscribers,
            'Active Subscribers' => (int) optional($subscribers)->active_subscribers,
            'Expired Subscribers' => (int) optional($subscribers)->expired_subscribers,
            'Total Combo Purchases' => (int) optional($purchases)->total,
            'Daily Purchases' => (int) optional($purchases)->daily,
            'Weekly Purchases' => (int) optional($purchases)->weekly,
            'Monthly Purchases' => (int) optional($purchases)->monthly,
            "Today's Purchases" => $fromDate ? (int) optional($purchases)->total : (int) optional($purchases)->today,
        ];
    }

    private function selectedRange(Request $request): array
    {
        $from = $request->query('from');
        $to = $request->query('to');
        if (($from === null || $from === '') && ($to === null || $to === '')) {
            return [null, null];
        }

        $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d'],
        ]);
        $fromDate = Carbon::createFromFormat('Y-m-d', $from)->startOfDay();
        $toDate = Carbon::createFromFormat('Y-m-d', $to)->startOfDay();
        if ($toDate->lt($fromDate)) {
            abort(422, 'The To Date must be on or after the From Date.');
        }

        return [$fromDate, $toDate->addDay()];
    }

    private function purchasesForRange(ComboPurchaseQuery $comboPurchases, ?Carbon $fromDate, ?Carbon $toDate)
    {
        $query = $comboPurchases->base();
        if ($fromDate) {
            $query->where('purchase_date', '>=', $fromDate)
                ->where('purchase_date', '<', $toDate);
        }

        return $query;
    }

    private function purchaseTrend($purchases, $now, ?Carbon $fromDate, ?Carbon $toDate): array
    {
        if ($fromDate) {
            return (clone $purchases)->selectRaw('DATE(purchase_date) as purchase_day, COUNT(*) as total')
                ->groupBy('purchase_day')->orderBy('purchase_day')->limit(366)->get()
                ->map(fn ($day) => ['label' => Carbon::parse($day->purchase_day)->format('d M'), 'total' => (int) $day->total])->all();
        }

        $startDate = $now->copy()->startOfDay()->subDays(6);
        $dailyCounts = (clone $purchases)->where('purchase_date', '>=', $startDate)
            ->where('purchase_date', '<=', $now)
            ->selectRaw('DATE(purchase_date) as purchase_day, COUNT(*) as total')
            ->groupBy('purchase_day')->pluck('total', 'purchase_day');

        return collect(range(0, 6))->map(function ($daysAgo) use ($now, $dailyCounts) {
            $date = $now->copy()->startOfDay()->subDays(6 - $daysAgo);
            return ['label' => $date->format('D'), 'total' => (int) ($dailyCounts[$date->toDateString()] ?? 0)];
        })->all();
    }
}
