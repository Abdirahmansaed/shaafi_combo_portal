<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class MockComboData
{
    public function purchases(): Collection
    {
        $now = Carbon::now();
        return collect([
            ['id'=>1008,'user_id'=>'USR-1042','name'=>'Fatima Mohamed','phone'=>'+252 63 700 1842','package'=>'Monthly Combo','type'=>'Monthly','purchase_date'=>$now->copy()->subHours(2),'expiry_date'=>$now->copy()->addDays(28),'status'=>'Active','amount'=>12.00,'payment_method'=>'EVC Plus','transaction_id'=>'EVC-7714208','payment_status'=>'Completed'],
            ['id'=>1007,'user_id'=>'USR-1008','name'=>'Ahmed Ali','phone'=>'+252 65 700 3108','package'=>'Weekly Combo','type'=>'Weekly','purchase_date'=>$now->copy()->subHours(5),'expiry_date'=>$now->copy()->addDays(6),'status'=>'Active','amount'=>4.00,'payment_method'=>'Zaad','transaction_id'=>'ZAD-7714207','payment_status'=>'Completed'],
            ['id'=>1006,'user_id'=>'USR-1019','name'=>'Abdi Rahman','phone'=>'+252 61 700 5219','package'=>'Daily Combo','type'=>'Daily','purchase_date'=>$now->copy()->subDay(),'expiry_date'=>$now->copy()->subHours(1),'status'=>'Expired','amount'=>1.00,'payment_method'=>'Sahal','transaction_id'=>'SHL-7714206','payment_status'=>'Completed'],
            ['id'=>1005,'user_id'=>'USR-1031','name'=>'Hassan Yusuf','phone'=>'+252 65 700 7631','package'=>'Monthly Combo','type'=>'Monthly','purchase_date'=>$now->copy()->subDays(2),'expiry_date'=>$now->copy()->addDays(28),'status'=>'Active','amount'=>12.00,'payment_method'=>'EVC Plus','transaction_id'=>'EVC-7714205','payment_status'=>'Completed'],
            ['id'=>1004,'user_id'=>'USR-1008','name'=>'Ahmed Ali','phone'=>'+252 65 700 3108','package'=>'Daily Combo','type'=>'Daily','purchase_date'=>$now->copy()->subDays(4),'expiry_date'=>$now->copy()->subDays(3),'status'=>'Expired','amount'=>1.00,'payment_method'=>'Zaad','transaction_id'=>'ZAD-7714204','payment_status'=>'Completed'],
            ['id'=>1003,'user_id'=>'USR-1055','name'=>'Mohamed Hassan','phone'=>'+252 63 700 9155','package'=>'Weekly Combo','type'=>'Weekly','purchase_date'=>$now->copy()->subDays(6),'expiry_date'=>$now->copy()->subHours(8),'status'=>'Expired','amount'=>4.00,'payment_method'=>'Sahal','transaction_id'=>'SHL-7714203','payment_status'=>'Completed'],
            ['id'=>1002,'user_id'=>'USR-1042','name'=>'Fatima Mohamed','phone'=>'+252 63 700 1842','package'=>'Weekly Combo','type'=>'Weekly','purchase_date'=>$now->copy()->subDays(10),'expiry_date'=>$now->copy()->subDays(3),'status'=>'Expired','amount'=>4.00,'payment_method'=>'EVC Plus','transaction_id'=>'EVC-7714202','payment_status'=>'Completed'],
            ['id'=>1001,'user_id'=>'USR-1064','name'=>'Asha Nur','phone'=>'+252 61 700 2464','package'=>'Daily Combo','type'=>'Daily','purchase_date'=>$now->copy()->subDays(12),'expiry_date'=>$now->copy()->subDays(11),'status'=>'Expired','amount'=>1.00,'payment_method'=>'Zaad','transaction_id'=>'ZAD-7714201','payment_status'=>'Completed'],
        ]);
    }

    public function purchase($id): array
    {
        return $this->purchases()->firstWhere('id', (int) $id) ?? abort(404);
    }

    public function subscribers(): Collection
    {
        return $this->purchases()->groupBy('user_id')->map(function ($history) {
            $latest = $history->sortByDesc('purchase_date')->first();
            return array_merge($latest, ['total_purchases' => $history->count(), 'history' => $history->sortByDesc('purchase_date')->values()]);
        })->values();
    }

    public function subscriber($id): array
    {
        return $this->subscribers()->firstWhere('user_id', $id) ?? abort(404);
    }
}
