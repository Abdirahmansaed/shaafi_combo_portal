<?php

namespace App\Http\Controllers;

use App\Services\ComboPurchaseQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ComboPurchaseController extends Controller
{
    public function index(Request $request, ComboPurchaseQuery $purchases)
    {
        $now = now();
        // Match the ordering to idx_combo_product_expiry_date
        // (product_id, expiry_date, purchase_date). MySQL can restrict the
        // product/expiry range before sorting only the matching candidates.
        $query = $purchases->base();
        if ($request->filled('q')) $purchases->whereMsisdnSearch($query, (string) $request->q);
        if ($productId = $purchases->productIdForPackage($request->package)) $query->where('product_id', $productId);
        if ($request->status === 'Active') $query->where('expiry_date', '>=', $now);
        if ($request->status === 'Expired') $query->where('expiry_date', '<', $now);
        if ($range = $request->range) {
            $start = $range === 'today' ? $now->copy()->startOfDay() : ($range === '7' ? $now->copy()->subDays(7) : ($range === '30' ? $now->copy()->subDays(30) : null));
            if ($start) $query->whereBetween('purchase_date', [$start, $now]);
        }
        // simplePaginate issues one LIMIT 21 query (no expensive COUNT(*)),
        // while selectPurchase keeps the result set to the page columns only.
        $records = $purchases->selectPurchase($query, $now)->orderByDesc('purchase_date')->simplePaginate(20)->appends($request->query());
        return view('combo-purchases.index', compact('records'));
    }

    public function show($id, ComboPurchaseQuery $purchases)
    {
        // This is a database query builder rather than an Eloquent builder,
        // so retrieve the row and explicitly return a 404 when it is absent.
        $purchase = $purchases->selectPurchase($purchases->base()->where('id', $id))->first();
        abort_if(is_null($purchase), 404);

        return view('combo-purchases.show', compact('purchase'));
    }
}
