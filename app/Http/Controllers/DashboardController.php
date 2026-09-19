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
        $selectedDate = $this->selectedDate($request);
        $purchases = $this->purchasesForDate($comboPurchases, $selectedDate);
        $stats = $this->liveStats($comboPurchases, $now, $selectedDate);
        $packageCounts = collect(self::PRODUCTS)->mapWithKeys(fn ($package, $productId) => [$package => $stats[$package.' Purchases']]);

        $purchaseTrend = $this->purchaseTrend($purchases, $now, $selectedDate);
        $recentPurchases = $comboPurchases->selectPurchase((clone $purchases), $now)
            ->orderByDesc('id')
            ->simplePaginate(10)->appends($request->only('date'));

        return view('dashboard.index', compact('stats', 'packageCounts', 'purchaseTrend', 'recentPurchases', 'selectedDate'));
    }

    /** One-second dashboard polling endpoint; every request reads live business data. */
    public function live(Request $request, ComboPurchaseQuery $comboPurchases)
    {
        $now = now();
        $selectedDate = $this->selectedDate($request);
        $purchases = $this->purchasesForDate($comboPurchases, $selectedDate);
        $recent = $comboPurchases->selectPurchase((clone $purchases), $now)
            ->orderByDesc('id')->limit(10)->get();
        $stats = $this->liveStats($comboPurchases, $now, $selectedDate);

        return response()->json([
            'stats' => $stats,
            'package_counts' => collect(self::PRODUCTS)->mapWithKeys(fn ($package, $productId) => [$package => $stats[$package.' Purchases']]),
            'purchase_trend' => $this->purchaseTrend($purchases, $now, $selectedDate),
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

    private function liveStats(ComboPurchaseQuery $comboPurchases, $now, ?Carbon $selectedDate = null): array
    {
        $purchases = $comboPurchases->selectSummary($this->purchasesForDate($comboPurchases, $selectedDate), $now, true)->first();
        $subscribers = $comboPurchases->selectSubscriberSummary($now, $selectedDate)->first();

        return [
            'Total Subscribers' => (int) optional($subscribers)->total_subscribers,
            'Active Subscribers' => (int) optional($subscribers)->active_subscribers,
            'Expired Subscribers' => (int) optional($subscribers)->expired_subscribers,
            'Total Combo Purchases' => (int) optional($purchases)->total,
            'Daily Purchases' => (int) optional($purchases)->daily,
            'Weekly Purchases' => (int) optional($purchases)->weekly,
            'Monthly Purchases' => (int) optional($purchases)->monthly,
            "Today's Purchases" => $selectedDate ? (int) optional($purchases)->total : (int) optional($purchases)->today,
        ];
    }

    private function selectedDate(Request $request): ?Carbon
    {
        $date = $request->query('date', 'all');
        if ($date === 'all' || $date === null || $date === '') {
            return null;
        }

        $request->validate(['date' => ['required', 'date_format:Y-m-d']]);

        return Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
    }

    private function purchasesForDate(ComboPurchaseQuery $comboPurchases, ?Carbon $selectedDate)
    {
        $query = $comboPurchases->base();
        if ($selectedDate) {
            $query->where('purchase_date', '>=', $selectedDate)
                ->where('purchase_date', '<', $selectedDate->copy()->addDay());
        }

        return $query;
    }

    private function purchaseTrend($purchases, $now, ?Carbon $selectedDate): array
    {
        if ($selectedDate) {
            return [[
                'label' => $selectedDate->format('d M'),
                'total' => (int) (clone $purchases)->count(),
            ]];
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
