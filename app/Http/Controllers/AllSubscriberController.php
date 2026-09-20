<?php

namespace App\Http\Controllers;

use App\Models\SubscriberAction;
use App\Services\ComboPurchaseQuery;
use Illuminate\Http\Request;

class AllSubscriberController extends Controller
{
    /** One current valid purchase per subscriber; expiry never excludes it. */
    public function index(Request $request, ComboPurchaseQuery $purchases)
    {
        $query = $purchases->latestSubscriberPurchases();
        if ($request->filled('q')) {
            $purchases->whereMsisdnSearch($query, (string) $request->q);
        }
        if ($productId = $purchases->productIdForPackage($request->package)) {
            $query->where('product_id', $productId);
        }

        $records = $purchases->selectPurchase($query, now())
            ->orderByDesc('purchase_date')->orderByDesc('id')
            ->simplePaginate(20)->appends($request->query());

        $actions = SubscriberAction::with('doneBy')->whereIn('purchase_id', $records->pluck('id'))
            ->get()->keyBy('purchase_id');
        foreach ($records as $record) {
            $record->action = $actions[$record->id] ?? null;
        }

        return view('all-subscribers.index', compact('records'));
    }
}
