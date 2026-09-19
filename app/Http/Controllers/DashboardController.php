<?php

namespace App\Http\Controllers;

use App\Services\ComboPurchaseQuery;

class DashboardController extends Controller
{
    private const PRODUCTS = [40720 => 'Daily', 40721 => 'Weekly', 40722 => 'Monthly'];

    public function index(ComboPurchaseQuery $comboPurchases)
    {
        set_time_limit(0);
        $now = now();
        $purchases = $comboPurchases->base();
        $stats = $this->liveStats($comboPurchases, $now);
        $packageCounts = collect(self::PRODUCTS)->mapWithKeys(fn ($package, $productId) => [$package => $stats[$package.' Purchases']]);

        $startDate = $now->copy()->startOfDay()->subDays(6);
        $dailyCounts = (clone $purchases)->whereBetween('purchase_date', [$startDate, $now])->selectRaw('DATE(purchase_date) as purchase_day, COUNT(*) as total')->groupBy('purchase_day')->pluck('total', 'purchase_day');
        $purchaseTrend = collect(range(0, 6))->map(function ($daysAgo) use ($now, $dailyCounts) {
            $date = $now->copy()->startOfDay()->subDays(6 - $daysAgo);
            return ['label' => $date->format('D'), 'total' => (int) ($dailyCounts[$date->toDateString()] ?? 0)];
        });
        $recentPurchases = $comboPurchases->selectPurchase((clone $purchases), $now)
            ->orderByDesc('id')
            ->simplePaginate(10);

        return view('dashboard.index', compact('stats', 'packageCounts', 'purchaseTrend', 'recentPurchases'));
    }

    /** One-second dashboard polling endpoint; every request reads live business data. */
    public function live(ComboPurchaseQuery $comboPurchases)
    {
        $now = now();
        $recent = $comboPurchases->selectPurchase($comboPurchases->base(), $now)
            ->orderByDesc('id')->limit(10)->get();

        return response()->json([
            'stats' => $this->liveStats($comboPurchases, $now),
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
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    private function liveStats(ComboPurchaseQuery $comboPurchases, $now): array
    {
        $purchases = $comboPurchases->selectSummary($comboPurchases->base(), $now, true)->first();
        $subscribers = $comboPurchases->selectSubscriberSummary($now)->first();

        return [
            'Total Subscribers' => (int) optional($subscribers)->total_subscribers,
            'Active Subscribers' => (int) optional($subscribers)->active_subscribers,
            'Expired Subscribers' => (int) optional($subscribers)->expired_subscribers,
            'Total Combo Purchases' => (int) optional($purchases)->total,
            'Daily Purchases' => (int) optional($purchases)->daily,
            'Weekly Purchases' => (int) optional($purchases)->weekly,
            'Monthly Purchases' => (int) optional($purchases)->monthly,
            "Today's Purchases" => (int) optional($purchases)->today,
        ];
    }
}
