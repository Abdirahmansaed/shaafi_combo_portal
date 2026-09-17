<?php

namespace App\Http\Controllers;

use App\Services\ComboPurchaseQuery;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Throwable;

class ComboPurchaseController extends Controller
{
    private const PDF_EXPORT_LIMIT = 10000;

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

    public function exportPdf(Request $request, ComboPurchaseQuery $purchases)
    {
        $this->validateFilters($request);
        $now = now();

        try {
            $rows = '';
            $lastId = 0;
            $exported = 0;
            // Dompdf must ultimately render one document in memory, so cap
            // it at a practical size. Each database read is still a bounded
            // 500-row batch: a broad filter never becomes one huge ->get().
            while ($exported < self::PDF_EXPORT_LIMIT) {
                $records = $purchases->filteredPurchases($request, $now)
                    ->where('id', '>', $lastId)->orderBy('id')->limit(500)->get();
                if ($records->isEmpty()) {
                    break;
                }
                foreach ($records as $purchase) {
                    $rows .= view('pdf.partials.combo-purchase-row', compact('purchase'))->render();
                    $lastId = $purchase->id;
                    ++$exported;
                }
            }
            if ($exported === self::PDF_EXPORT_LIMIT && $purchases->filteredPurchases($request, $now)->where('id', '>', $lastId)->exists()) {
                return back()->withInput()->with('error', 'This export exceeds 10,000 records. Please narrow the filters and try again.');
            }

            return Pdf::loadView('pdf.combo-purchases', [
                'rows' => $rows,
                'filters' => $this->filterLabels($request),
                'generatedAt' => $now,
            ])->setPaper('a4', 'landscape')->download('combo-purchases-report-'.$now->format('Y-m-d').'.pdf');
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->with('error', 'The Combo Purchases PDF could not be generated. Please try again.');
        }
    }

    private function filterLabels(Request $request): array
    {
        return array_filter([
            'MSISDN' => $request->filled('q') ? $request->q : null,
            'Package' => in_array($request->package, ['Daily', 'Weekly', 'Monthly'], true) ? $request->package : null,
            'Status' => in_array($request->status, ['Active', 'Expired'], true) ? $request->status : null,
            'Date range' => ['today' => 'Today', '7' => 'Last 7 days', '30' => 'Last 30 days'][$request->range] ?? null,
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
