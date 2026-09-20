<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComboPurchaseQuery
{
    public const PRODUCT_IDS = ['40720', '40721', '40722'];
    public const PRODUCTS = [40720 => 'Daily', 40721 => 'Weekly', 40722 => 'Monthly'];
    private const MINIMUM_SUFFIX_LENGTH = 7;

    public function base(): Builder
    {
        return DB::connection('mysql_live')->query()
            ->fromSub($this->purchaseUnion(), 'live_purchases');
    }

    /**
     * Return validated tables from the comma-separated LIVE_PURCHASE_TABLE
     * configuration value. Empty items are ignored deliberately.
     */
    public function tableNames(): array
    {
        $tables = array_values(array_filter(array_map('trim', explode(',', (string) config('database.live_purchase_table'))), function (string $table) {
            return $table !== '';
        }));

        if ($tables === []) {
            throw new \RuntimeException('LIVE_PURCHASE_TABLE must contain at least one table name.');
        }

        foreach ($tables as $table) {
            if (! preg_match('/^[A-Za-z0-9_]+$/', $table)) {
                throw new \InvalidArgumentException('LIVE_PURCHASE_TABLE contains an invalid table name.');
            }
        }

        return $tables;
    }

    /** Obtain the highest source ID without changing the live source table. */
    public function latestId()
    {
        // Each source has its own indexed primary key. Fetching one MAX per
        // table avoids materialising every source row in a derived UNION.
        return collect($this->tableNames())
            ->map(fn (string $table) => DB::connection('mysql_live')->table($table)->max('id'))
            ->filter(fn ($id) => $id !== null)
            ->max();
    }

    public function selectPurchase(Builder $query, $now = null): Builder
    {
        $now = $now ?: now();

        return $query->select(['id', 'msisdn', 'product_id', 'purchase_date', 'expiry_date', 'price', 'amount_mb'])
            ->selectRaw("CASE product_id WHEN 40720 THEN 'Daily' WHEN 40721 THEN 'Weekly' WHEN 40722 THEN 'Monthly' END as package")
            ->selectRaw("CASE WHEN expiry_date >= ? THEN 'Active' ELSE 'Expired' END as status", [$now]);
    }

    /**
     * Apply the portal's Combo Purchases filters.  The listing and PDF export
     * deliberately call this same method so their result sets stay identical.
     */
    public function filteredPurchases(Request $request, $now = null): Builder
    {
        $now = $now ?: now();
        $query = $this->base();

        if ($request->filled('q')) {
            $this->whereMsisdnSearch($query, (string) $request->q);
        }
        if ($productId = $this->productIdForPackage($request->package)) {
            $query->where('product_id', $productId);
        }
        if ($request->status === 'Active') {
            $query->where('expiry_date', '>=', $now);
        }
        if ($request->status === 'Expired') {
            $query->where('expiry_date', '<', $now);
        }
        if ($range = $request->range) {
            $start = $range === 'today' ? $now->copy()->startOfDay() : ($range === '7' ? $now->copy()->subDays(7) : ($range === '30' ? $now->copy()->subDays(30) : null));
            if ($start) {
                $query->whereBetween('purchase_date', [$start, $now]);
            }
        }

        return $this->selectPurchase($query, $now);
    }

    /** Build aggregate counters using the same valid-purchase/status rules. */
    public function selectSummary(Builder $query, $now, bool $includeToday = false): Builder
    {
        $query->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN product_id = 40720 THEN 1 ELSE 0 END) as daily')
            ->selectRaw('SUM(CASE WHEN product_id = 40721 THEN 1 ELSE 0 END) as weekly')
            ->selectRaw('SUM(CASE WHEN product_id = 40722 THEN 1 ELSE 0 END) as monthly')
            ->selectRaw('SUM(CASE WHEN expiry_date >= ? THEN 1 ELSE 0 END) as active', [$now])
            ->selectRaw('SUM(CASE WHEN expiry_date < ? THEN 1 ELSE 0 END) as expired', [$now]);

        if ($includeToday) {
            $query->selectRaw('SUM(CASE WHEN purchase_date >= ? AND purchase_date <= ? THEN 1 ELSE 0 END) as today', [$now->copy()->startOfDay(), $now->copy()->endOfDay()]);
        }

        return $query;
    }

    /**
     * Aggregate each source table in place, then add the tiny result rows in
     * PHP. This is intentionally separate from base(): base() is retained
     * for result listings, where individual purchase records are required.
     */
    public function summary($now, $dateStart = null, $dateEnd = null, bool $includeToday = false): \stdClass
    {
        $totals = (object) [
            'total' => 0, 'daily' => 0, 'weekly' => 0, 'monthly' => 0,
            'active' => 0, 'expired' => 0, 'today' => 0, 'revenue' => 0.0,
        ];

        foreach ($this->tableNames() as $table) {
            $query = $this->validPurchasesForTable(DB::connection('mysql_live'), $table);
            $this->applyPurchaseDateRange($query, $dateStart, $dateEnd);
            $row = $this->selectSummary($query, $now, $includeToday)
                ->selectRaw('COALESCE(SUM(price), 0) as revenue')
                ->first();

            foreach (['total', 'daily', 'weekly', 'monthly', 'active', 'expired', 'today'] as $field) {
                $totals->{$field} += (int) ($row->{$field} ?? 0);
            }
            $totals->revenue += (float) ($row->revenue ?? 0);
        }

        return $totals;
    }

    /**
     * Return the current subscriber counts from each MSISDN's latest valid
     * Combo purchase.  The database ranks rows; PHP receives one aggregate
     * result rather than a collection of purchase records.
     */
    public function selectSubscriberSummary($now, $dateStart = null, $dateEnd = null): Builder
    {
        // Rank each source table first. The UNION consequently contains at
        // most one candidate per MSISDN per table, rather than every purchase.
        $latestPurchases = DB::connection('mysql_live')->query()
            ->fromSub($this->subscriberCandidateUnion($dateStart, $dateEnd), 'subscriber_candidates')
            ->select(['msisdn', 'expiry_date'])
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY msisdn ORDER BY purchase_date DESC, id DESC) as purchase_rank');

        return DB::connection('mysql_live')->query()
            ->fromSub($latestPurchases, 'latest_purchases')
            ->where('purchase_rank', 1)
            ->selectRaw('COUNT(*) as total_subscribers')
            ->selectRaw('SUM(CASE WHEN expiry_date >= ? THEN 1 ELSE 0 END) as active_subscribers', [$now])
            ->selectRaw('SUM(CASE WHEN expiry_date < ? THEN 1 ELSE 0 END) as expired_subscribers', [$now]);
    }

    /** Aggregate the seven-day (or requested) trend within every source table. */
    public function trendByDay($from, $to)
    {
        $totals = [];
        foreach ($this->tableNames() as $table) {
            $rows = $this->validPurchasesForTable(DB::connection('mysql_live'), $table)
                ->whereBetween('purchase_date', [$from, $to])
                ->selectRaw('DATE(purchase_date) as day, COUNT(*) as total')
                ->groupBy('day')->get();
            foreach ($rows as $row) {
                $totals[$row->day] = ($totals[$row->day] ?? 0) + (int) $row->total;
            }
        }

        return collect($totals)->sortKeys()->map(fn ($total, $day) => (object) [
            'day' => $day,
            'total' => $total,
        ])->values();
    }

    /**
     * One latest valid row per canonical MSISDN across every configured
     * source. Each table is reduced first, so the final rank never receives
     * every raw purchase from every monthly table.
     */
    public function latestSubscriberPurchases(): Builder
    {
        $candidates = DB::connection('mysql_live')->query()
            ->fromSub($this->subscriberCandidateUnion(), 'subscriber_candidates')
            ->select('subscriber_candidates.*')
            ->selectRaw("ROW_NUMBER() OVER (PARTITION BY REPLACE(msisdn, '+', '') ORDER BY purchase_date DESC, id DESC) as purchase_rank");

        return DB::connection('mysql_live')->query()
            ->fromSub($candidates, 'latest_subscriber_purchases')
            ->where('purchase_rank', 1);
    }

    /**
     * Keep dashboard pages bounded: take only the newest page-sized
     * candidate set from each table, then order that small SQL union globally.
     */
    public function recentPurchases(int $perPage, int $page = 1, $dateStart = null, $dateEnd = null): Builder
    {
        $limit = ($perPage * max(1, $page)) + 1;
        $tables = $this->tableNames();
        $connection = DB::connection('mysql_live');
        $union = $this->recentPurchasesForTable($connection, array_shift($tables), $limit, $dateStart, $dateEnd);

        foreach ($tables as $table) {
            $union->unionAll($this->recentPurchasesForTable($connection, $table, $limit, $dateStart, $dateEnd));
        }

        return $connection->query()->fromSub($union, 'recent_live_purchases')
            ->orderByDesc('purchase_date')->orderByDesc('id');
    }

    /**
     * Return one subscriber's latest valid Combo purchase.
     *
     * A valid purchase has a supported product and a non-null expiry.  The
     * timestamp comparison in selectPurchase() deliberately uses the full
     * expiry datetime, not a date-only comparison.
     */
    public function latestValidPurchaseForMsisdn(string $msisdn, $now = null)
    {
        $digits = preg_replace('/\D+/', '', trim($msisdn));

        return $this->selectPurchase(
            $this->base()->whereIn('msisdn', $this->internationalVariants($digits)),
            $now
        )
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->first();
    }

    public function productIdForPackage($package): ?string
    {
        $productId = array_search(ucfirst(strtolower((string) $package)), self::PRODUCTS, true);

        return $productId === false ? null : (string) $productId;
    }

    /**
     * Apply a shared, index-aware MSISDN search to a valid-purchases query.
     *
     * Full international and nine-digit local numbers are exact lookups. A
     * short suffix is deliberately restricted and is returned through the
     * caller's paginator rather than being loaded into PHP.
     */
    public function whereMsisdnSearch(Builder $query, string $input): Builder
    {
        $digits = preg_replace('/\D+/', '', trim($input));

        if ($digits === '') {
            return $query->whereRaw('1 = 0');
        }

        if (substr($digits, 0, 3) === '252') {
            if (strlen($digits) >= 12) {
                return $query->whereIn('msisdn', $this->internationalVariants($digits));
            }

            return $this->whereInternationalPrefix($query, $digits);
        }

        if (strlen($digits) === 9) {
            return $query->whereIn('msisdn', $this->internationalVariants('252'.$digits));
        }

        if (strlen($digits) < self::MINIMUM_SUFFIX_LENGTH) {
            return $query->whereRaw('1 = 0');
        }

        // There is no reverse-MSISDN index, so arbitrary suffix searching
        // cannot be an exact seek. Keep the fixed country-code prefix and let
        // the controller's simple paginator issue LIMIT 21, never ->get().
        return $query->where(function (Builder $msisdnQuery) use ($digits) {
            $msisdnQuery->where('msisdn', 'like', '252%'.$digits)
                ->orWhere('msisdn', 'like', '+252%'.$digits);
        });
    }

    private function internationalVariants(string $international): array
    {
        return [$international, '+'.$international];
    }

    private function whereInternationalPrefix(Builder $query, string $digits): Builder
    {
        return $query->where(function (Builder $msisdnQuery) use ($digits) {
            $msisdnQuery->where('msisdn', 'like', $digits.'%')
                ->orWhere('msisdn', 'like', '+'.$digits.'%');
        });
    }

    /** Combine every configured source table without removing duplicate rows. */
    private function tableUnion(): Builder
    {
        $tables = $this->tableNames();
        $connection = DB::connection('mysql_live');
        $union = $connection->table(array_shift($tables));

        foreach ($tables as $table) {
            $union->unionAll($connection->table($table));
        }

        return $union;
    }

    /** Apply the portal's valid-purchase rules to each table before UNION ALL. */
    private function purchaseUnion(): Builder
    {
        $tables = $this->tableNames();
        $connection = DB::connection('mysql_live');
        $union = $this->validPurchasesForTable($connection, array_shift($tables));

        foreach ($tables as $table) {
            $union->unionAll($this->validPurchasesForTable($connection, $table));
        }

        return $union;
    }

    private function validPurchasesForTable($connection, string $table): Builder
    {
        return $connection->table($table)
            // product_id is VARCHAR in the source table, so bind its values as
            // strings. This is important once the product index is added.
            ->whereIn('product_id', self::PRODUCT_IDS)
            ->whereNotNull('expiry_date');
    }

    private function applyPurchaseDateRange(Builder $query, $dateStart = null, $dateEnd = null): Builder
    {
        if ($dateStart) {
            $query->where('purchase_date', '>=', $dateStart)
                ->where('purchase_date', '<', $dateEnd);
        }

        return $query;
    }

    private function subscriberCandidateUnion($dateStart = null, $dateEnd = null): Builder
    {
        $tables = $this->tableNames();
        $connection = DB::connection('mysql_live');
        $union = $this->latestSubscriberCandidateForTable($connection, array_shift($tables), $dateStart, $dateEnd);

        foreach ($tables as $table) {
            $union->unionAll($this->latestSubscriberCandidateForTable($connection, $table, $dateStart, $dateEnd));
        }

        return $union;
    }

    private function latestSubscriberCandidateForTable($connection, string $table, $dateStart, $dateEnd): Builder
    {
        $purchases = $this->validPurchasesForTable($connection, $table);
        $this->applyPurchaseDateRange($purchases, $dateStart, $dateEnd);

        $ranked = $purchases->select(['id', 'msisdn', 'product_id', 'purchase_date', 'expiry_date', 'price', 'amount_mb'])
            ->selectRaw("ROW_NUMBER() OVER (PARTITION BY REPLACE(msisdn, '+', '') ORDER BY purchase_date DESC, id DESC) as purchase_rank");

        return $connection->query()->fromSub($ranked, 'table_latest_purchases')
            ->where('purchase_rank', 1)
            ->select(['id', 'msisdn', 'product_id', 'purchase_date', 'expiry_date', 'price', 'amount_mb']);
    }

    private function recentPurchasesForTable($connection, string $table, int $limit, $dateStart, $dateEnd): Builder
    {
        $purchases = $this->validPurchasesForTable($connection, $table);
        $this->applyPurchaseDateRange($purchases, $dateStart, $dateEnd);

        return $purchases->select(['id', 'msisdn', 'product_id', 'purchase_date', 'expiry_date', 'price', 'amount_mb'])
            ->orderByDesc('purchase_date')->orderByDesc('id')->limit($limit);
    }
}
