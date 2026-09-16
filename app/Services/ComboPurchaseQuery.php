<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ComboPurchaseQuery
{
    public const TABLE = 'data_purchase_07_2026';
    public const PRODUCT_IDS = ['40720', '40721', '40722'];
    public const PRODUCTS = [40720 => 'Daily', 40721 => 'Weekly', 40722 => 'Monthly'];
    private const MINIMUM_SUFFIX_LENGTH = 7;

    public function base(): Builder
    {
        return DB::connection('mysql_business')->table(self::TABLE)
            // product_id is VARCHAR in the source table, so bind its values as
            // strings. This is important once the product index is added.
            ->whereIn('product_id', self::PRODUCT_IDS)
            ->whereNotNull('expiry_date');
    }

    public function selectPurchase(Builder $query, $now = null): Builder
    {
        $now = $now ?: now();

        return $query->select(['id', 'msisdn', 'product_id', 'purchase_date', 'expiry_date', 'price', 'amount_mb'])
            ->selectRaw("CASE product_id WHEN 40720 THEN 'Daily' WHEN 40721 THEN 'Weekly' WHEN 40722 THEN 'Monthly' END as package")
            ->selectRaw("CASE WHEN expiry_date >= ? THEN 'Active' ELSE 'Expired' END as status", [$now]);
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
}
