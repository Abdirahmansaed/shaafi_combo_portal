<?php

namespace App\Http\Controllers;

use App\Models\ActiveSubscriber;
use App\Models\SubscriberAction;
use App\Services\ComboPurchaseQuery;
use Illuminate\Http\Request;

class ActiveSubscriberController extends Controller
{
    public function index(Request $request, ComboPurchaseQuery $purchases)
    {
        $query = $purchases->base()->where('expiry_date', '>=', now());

        if ($request->filled('q')) {
            $purchases->whereMsisdnSearch($query, (string) $request->q);
        }

        if (in_array($request->package, ['DAILY', 'WEEKLY', 'MONTHLY'], true)) {
            $productId = $purchases->productIdForPackage(strtolower($request->package));
            $query->where('product_id', $productId);
        }

        $purchasesPage = $query->select(['id', 'msisdn', 'product_id', 'purchase_date', 'expiry_date', 'price'])
            ->orderByDesc('id')
            ->simplePaginate(20)
            ->appends($request->query());

        $tiers = ComboPurchaseQuery::PRODUCTS;
        foreach ($purchasesPage as $purchase) {
            ActiveSubscriber::updateOrCreate(
                ['business_purchase_id' => $purchase->id],
                [
                    'subscriber_number' => $purchase->msisdn,
                    'package_tier' => strtoupper($tiers[$purchase->product_id]),
                    'purchase_date' => $purchase->purchase_date,
                    'expire_date' => $purchase->expiry_date,
                ]
            );
        }

        $tracking = ActiveSubscriber::with('doneBy')->whereIn('business_purchase_id', $purchasesPage->pluck('id'))
            ->get()->keyBy('business_purchase_id');
        foreach ($purchasesPage as $purchase) {
            $purchase->tracking = $tracking[$purchase->id];
        }

        return view('active-subscribers.index', ['records' => $purchasesPage]);
    }

    public function complete(Request $request, ActiveSubscriber $activeSubscriber)
    {
        // The conditional update makes completion atomic: only the first
        // authenticated agent can transition a pending record.
        $updated = ActiveSubscriber::whereKey($activeSubscriber->id)
            ->where('action_status', 'PENDING')
            ->update([
                'action_status' => 'COMPLETED',
                'done_by' => $request->user()->id,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

        if (! $updated) {
            return response()->json(['message' => 'This subscriber was already completed by another agent.'], 409);
        }

        $activeSubscriber->refresh()->load('doneBy');
        // Agent Performance reads subscriber_actions. Keep the active-page
        // workflow and report source in sync without touching live purchases.
        SubscriberAction::updateOrCreate(
            ['purchase_id' => $activeSubscriber->business_purchase_id],
            [
                'msisdn' => $activeSubscriber->subscriber_number,
                'agent_status' => 'COMPLETED',
                'done_by' => $activeSubscriber->done_by,
                'completed_at' => $activeSubscriber->completed_at,
            ]
        );

        return response()->json([
            'message' => 'Subscriber marked as completed.',
            'status' => $activeSubscriber->action_status,
            'done_by' => $activeSubscriber->doneBy->displayName(),
            'completed_at' => optional($activeSubscriber->completed_at)->format('d-M-Y H:i'),
        ]);
    }
}
