<x-layouts.app title="Dashboard">
<div class="mb-6 flex items-end justify-between"><p class="text-sm text-slate-500">An overview of Combo Package activity and subscriptions.</p><p id="dashboard-last-updated" class="text-xs text-slate-400">Last updated: {{ now()->format('d M Y, H:i:s') }}</p></div>
<div class="grid grid-cols-2 gap-3 lg:grid-cols-4 xl:grid-cols-8">@foreach($stats as $label=>$value)<x-stat-card :label="$label" :value="$value" :live-key="$label" :icon="str_contains($label,'Daily')?'sun':(str_contains($label,'Weekly')?'calendar-days':(str_contains($label,'Monthly')?'calendar':'users'))"/>@endforeach</div>
<div class="mt-6 grid gap-6 xl:grid-cols-2"><section class="rounded-lg border border-slate-200 bg-white p-5"><div class="mb-5 flex items-center justify-between"><h2 class="font-semibold">Purchases by package</h2><span class="text-xs text-slate-500">All time</span></div>@php($largestPackageCount=max(1,$packageCounts->max()))@foreach($packageCounts as $name=>$count)<div class="mb-5"><div class="mb-2 flex justify-between text-sm"><span>{{ $name }} Combo</span><span class="font-semibold">{{ number_format($count) }}</span></div><div class="h-2 rounded bg-slate-100"><div class="chart-bar h-2 rounded" style="width:{{ ($count/$largestPackageCount)*100 }}%"></div></div></div>@endforeach</section>
<section class="rounded-lg border border-slate-200 bg-white p-5"><div class="mb-5 flex items-center justify-between"><h2 class="font-semibold">Purchases over time</h2><span class="text-xs text-slate-500">Last 7 days</span></div>@php($largestTrendCount=max(1,$purchaseTrend->max('total')))<div class="flex h-40 items-end justify-between gap-2">@foreach($purchaseTrend as $day)<div class="flex flex-1 flex-col items-center gap-2"><div class="w-full rounded-t bg-teal-500" style="height:{{ max(2,($day['total']/$largestTrendCount)*140) }}px"></div><span class="text-[10px] text-slate-500">{{ $day['label'] }}</span></div>@endforeach</div></section></div>
<section class="mt-6 overflow-hidden rounded-lg border border-slate-200 bg-white"><div class="flex items-center justify-between border-b border-slate-200 p-5"><div><h2 class="font-semibold">Recent purchases</h2><p class="mt-1 text-sm text-slate-500">Latest Combo Package transactions</p></div><a class="text-sm font-medium text-teal-700" href="{{ route('combo-purchases.index') }}">View all</a></div><div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">ID</th><th class="px-5 py-3">MSISDN</th><th class="px-5 py-3">Package</th><th class="px-5 py-3">Purchase date</th><th class="px-5 py-3">Expiry date</th><th class="px-5 py-3">Price</th><th class="px-5 py-3">Amount MB</th><th class="px-5 py-3">Status</th></tr></thead><tbody id="dashboard-recent-purchases">@forelse($recentPurchases as $purchase)<tr class="table-row border-t border-slate-100"><td class="px-5 py-4 font-medium">{{ $purchase->id }}</td><td class="px-5 py-4 text-slate-500">{{ $purchase->msisdn }}</td><td class="px-5 py-4">{{ $purchase->package }}</td><td class="px-5 py-4 text-slate-500">{{ \Carbon\Carbon::parse($purchase->purchase_date)->format('d M Y, H:i') }}</td><td class="px-5 py-4 text-slate-500">{{ \Carbon\Carbon::parse($purchase->expiry_date)->format('d M Y, H:i') }}</td><td class="px-5 py-4">{{ number_format($purchase->price,2) }}</td><td class="px-5 py-4">{{ number_format($purchase->amount_mb) }}</td><td class="px-5 py-4"><x-status :value="$purchase->status"/></td></tr>@empty<tr><td colspan="8" class="px-5 py-10 text-center text-slate-500">No Combo Package purchases found.</td></tr>@endforelse</tbody></table></div><div class="border-t border-slate-200 px-5 py-3">{{ $recentPurchases->links() }}</div></section>
<script>
(() => {
    if (window.shaafiDashboardPoller) clearInterval(window.shaafiDashboardPoller);
    let inFlight = false;
    let recentSignature = '';
    const lastUpdated = document.getElementById('dashboard-last-updated');
    const recentBody = document.getElementById('dashboard-recent-purchases');
    const money = new Intl.NumberFormat(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});

    function cell(value, className = '') { const td = document.createElement('td'); td.className = `px-5 py-4 ${className}`; td.textContent = value; return td; }
    function renderRecent(purchases) {
        const signature = JSON.stringify(purchases);
        if (signature === recentSignature) return;
        recentSignature = signature;
        recentBody.replaceChildren();
        purchases.forEach(purchase => {
            const row = document.createElement('tr'); row.className = 'table-row border-t border-slate-100';
            row.append(cell(purchase.id, 'font-medium'), cell(purchase.msisdn, 'text-slate-500'), cell(purchase.package), cell(purchase.purchase_date, 'text-slate-500'), cell(purchase.expiry_date, 'text-slate-500'), cell(money.format(purchase.price)), cell(Number(purchase.amount_mb).toLocaleString()));
            const status = document.createElement('span'); status.className = purchase.status === 'Active' ? 'inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700' : 'inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600'; status.textContent = purchase.status;
            const statusCell = cell(''); statusCell.append(status); row.append(statusCell); recentBody.append(row);
        });
    }
    async function refreshDashboard() {
        if (inFlight) return;
        inFlight = true;
        try {
            const response = await fetch('{{ route('dashboard.live') }}', {headers: {'Accept': 'application/json', 'Cache-Control': 'no-cache'}, credentials: 'same-origin', cache: 'no-store'});
            if (!response.ok) throw new Error('Live update failed');
            const data = await response.json();
            Object.entries(data.stats).forEach(([label, value]) => document.querySelectorAll('[data-dashboard-stat]').forEach(node => { if (node.dataset.dashboardStat === label && node.textContent !== String(value)) node.textContent = Number(value).toLocaleString(); }));
            renderRecent(data.recent_purchases);
            lastUpdated.textContent = `Last updated: ${data.last_updated}`;
        } catch (error) {
            console.error('Dashboard live update failed.', error);
        } finally { inFlight = false; }
    }
    refreshDashboard();
    window.shaafiDashboardPoller = setInterval(refreshDashboard, 1000);
    window.addEventListener('beforeunload', () => clearInterval(window.shaafiDashboardPoller), {once: true});
})();
</script>
</x-layouts.app>
