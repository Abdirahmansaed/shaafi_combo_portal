<?php

namespace App\Http\Controllers;

use App\Models\SubscriberAction;
use App\Services\ComboPurchaseQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SubscriberController extends Controller
{
    public function index(Request $request, ComboPurchaseQuery $purchases)
    {
        if ($request->filled('q')) {
            // A searched subscriber may have purchases older than the recent
            // directory window, so do not apply that window to this lookup.
            $base = $purchases->whereMsisdnSearch($purchases->base(), (string) $request->q);
        } else {
            // `id` is indexed; using it for the bounded subscriber directory
            // avoids grouping all 36M rows on every unfiltered page request.
            $latestId = Cache::remember('subscribers.latest-id', now()->addHour(), fn () => DB::connection('mysql_business')->table(ComboPurchaseQuery::TABLE)->max('id'));
            $base = $purchases->base()->where('id', '>=', max(0, $latestId - 100000));
        }
        // IDs are not a reliable proxy for when a customer purchased a
        // package. Group by the latest valid purchase datetime, then use the
        // shared query below to obtain that subscriber's exact latest record.
        // A number stored as +252... and 252... is one subscriber. Group on
        // its canonical digits so the directory has exactly one row for it.
        $subscribers = $base
            ->selectRaw("REPLACE(msisdn, '+', '') as subscriber_key, MIN(msisdn) as msisdn, MAX(purchase_date) as latest_purchase_date, COUNT(*) as total_purchases")
            ->groupByRaw("REPLACE(msisdn, '+', '')")
            ->orderByDesc('latest_purchase_date')
            ->simplePaginate(20)
            ->appends($request->query());
        foreach ($subscribers as $subscriber) {
            $subscriber->current = $purchases->latestValidPurchaseForMsisdn($subscriber->subscriber_key);
        }

        $actions = SubscriberAction::with('doneBy')->whereIn('purchase_id', $subscribers->pluck('current.id')->filter())->get()->keyBy('purchase_id');
        foreach ($subscribers as $subscriber) {
            $subscriber->action = $actions[$subscriber->current->id] ?? null;
            $subscriber->agent_status = optional($subscriber->action)->agent_status ?: 'PENDING';
        }

        // Agent work is portal-owned, so this filter is applied only to the
        // paginated page of valid business purchases—not through a cross-DB join.
        if (in_array($request->subscription_status, ['Active', 'Expired'], true) || in_array($request->agent_status, ['PENDING', 'COMPLETED'], true)) {
            $subscribers->setCollection($subscribers->getCollection()->filter(function ($subscriber) use ($request) {
                return (! in_array($request->subscription_status, ['Active', 'Expired'], true) || $subscriber->current->status === $request->subscription_status)
                    && (! in_array($request->agent_status, ['PENDING', 'COMPLETED'], true) || $subscriber->agent_status === $request->agent_status);
            })->values());
        }
        return view('subscribers.index', compact('subscribers'));
    }

    public function show($id, ComboPurchaseQuery $purchases)
    {
        $history = $purchases->selectPurchase($purchases->base()->where('msisdn', $id))->orderByDesc('purchase_date')->orderByDesc('id')->simplePaginate(20);
        abort_if($history->isEmpty(), 404);
        $actions = SubscriberAction::with('doneBy')->whereIn('purchase_id', $history->pluck('id'))->get()->keyBy('purchase_id');
        foreach ($history as $purchase) {
            $purchase->action = $actions[$purchase->id] ?? null;
            $purchase->agent_status = optional($purchase->action)->agent_status ?: 'PENDING';
        }
        return view('subscribers.show', ['subscriber' => $history->first(), 'history' => $history]);
    }

    public function complete($purchaseId, ComboPurchaseQuery $purchases)
    {
        $purchase = $purchases->base()->where('id', $purchaseId)->first(['id', 'msisdn']);
        abort_if(! $purchase, 404);

        $action = SubscriberAction::firstOrCreate(
            ['purchase_id' => $purchase->id],
            ['msisdn' => $purchase->msisdn, 'agent_status' => 'PENDING']
        );

        if ($action->agent_status !== 'COMPLETED') {
            $action->update([
                'agent_status' => 'COMPLETED',
                'done_by' => auth()->id(),
                'completed_at' => now(),
            ]);
        }

        return back()->with('success', 'Agent work marked as completed.');
    }
}
