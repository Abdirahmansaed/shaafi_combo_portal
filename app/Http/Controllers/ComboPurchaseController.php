<?php

namespace App\Http\Controllers;

use App\Models\SubscriberAction;
use App\Services\ComboPurchaseQuery;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class ComboPurchaseController extends Controller
{
    private const EXPORT_CHUNK_SIZE = 500;

    public function index(Request $request, ComboPurchaseQuery $purchases)
    {
        $this->validateFilters($request);
        $now = now();
        // Match the ordering to idx_combo_product_expiry_date
        // (product_id, expiry_date, purchase_date). MySQL can restrict the
        // product/expiry range before sorting only the matching candidates.
        // simplePaginate issues one LIMIT 21 query (no expensive COUNT(*)),
        // while selectPurchase keeps the result set to the page columns only.
        $records = $purchases->filteredPurchases($request, $now)->orderByDesc('purchase_date')->simplePaginate(20)->appends($request->query());
        return view('combo-purchases.index', compact('records'));
    }

    public function exportExcel(Request $request, ComboPurchaseQuery $purchases)
    {
        $this->validateFilters($request);
        $now = now();

        $filename = 'combo-purchases-report-'.$now->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($purchases, $request, $now) {
            // OpenSpout writes each XLSX row directly to the response.
            // Combined with bounded database batches, this keeps an
            // unfiltered export of millions of purchases out of PHP memory.
            $writer = new Writer();
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues([
                'ID', 'MSISDN', 'Package', 'Purchase Date', 'Expiry Date',
                'Price', 'Status', 'Action', 'Done By',
            ]));

            $lastId = 0;
            while (true) {
                // Reuse the listing's exact validated query, valid-data
                // rules, filters, and status calculation. Keyset pagination
                // avoids costly offsets on the live table.
                $records = $purchases->filteredPurchases($request, $now)
                    ->where('id', '>', $lastId)
                    ->orderBy('id')
                    ->limit(self::EXPORT_CHUNK_SIZE)
                    ->get();

                if ($records->isEmpty()) {
                    break;
                }

                $actions = SubscriberAction::with('doneBy')
                    ->whereIn('purchase_id', $records->pluck('id'))
                    ->get()
                    ->keyBy('purchase_id');

                foreach ($records as $purchase) {
                    $action = $actions[$purchase->id] ?? null;
                    $agentStatus = optional($action)->agent_status ?: 'PENDING';

                    $writer->addRow(Row::fromValues([
                        $purchase->id,
                        $purchase->msisdn,
                        $purchase->package,
                        $purchase->purchase_date,
                        $purchase->expiry_date,
                        (float) $purchase->price,
                        $purchase->status,
                        ucfirst(strtolower($agentStatus)),
                        optional(optional($action)->doneBy)->displayName() ?: '—',
                    ]));
                }

                $lastId = $records->last()->id;
            }

            $writer->close();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function validateFilters(Request $request): void
    {
        $request->validate([
            'q' => 'nullable|string|max:20',
            'package' => 'nullable|in:Daily,Weekly,Monthly',
            'status' => 'nullable|in:Active,Expired',
            'range' => 'nullable|in:today,7,30',
        ]);
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
