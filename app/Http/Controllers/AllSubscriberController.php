<?php

namespace App\Http\Controllers;

use App\Models\ActiveSubscriber;
use App\Models\SubscriberAction;
use App\Services\ComboPurchaseQuery;
use Illuminate\Http\Request;

class AllSubscriberController extends Controller
{
    /** Complete purchase history; expiry never excludes a row from this page. */
    public function index(Request $request, ComboPurchaseQuery $purchases)
    {
        $query = $purchases->base();
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

    public function complete(Request $request, $purchaseId, ComboPurchaseQuery $purchases)
    {
        $purchase = $purchases->base()->where('id', $purchaseId)
            ->first(['id', 'msisdn']);
        abort_if(! $purchase, 404);

        SubscriberAction::firstOrCreate(
            ['purchase_id' => $purchase->id],
            ['msisdn' => $purchase->msisdn, 'agent_status' => 'PENDING']
        );
        $updated = SubscriberAction::where('purchase_id', $purchase->id)
            ->where('agent_status', 'PENDING')
            ->update([
                'agent_status' => 'COMPLETED',
                'done_by' => $request->user()->id,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

        if (! $updated) {
            return response()->json(['message' => 'This subscriber was already completed by another agent.'], 409);
        }

        // Keep an already-created Active Subscribers tracking row consistent
        // when this work is completed from full history.
        ActiveSubscriber::where('business_purchase_id', $purchase->id)
            ->where('action_status', 'PENDING')
            ->update([
                'action_status' => 'COMPLETED',
                'done_by' => $request->user()->id,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

        $action = SubscriberAction::with('doneBy')->where('purchase_id', $purchase->id)->firstOrFail();

        return response()->json([
            'message' => 'Subscriber marked as completed.',
            'status' => $action->agent_status,
            'done_by' => $action->doneBy->displayName(),
            'completed_at' => optional($action->completed_at)->format('d-M-Y H:i'),
        ]);
    }
}
