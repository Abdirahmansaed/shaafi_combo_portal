<x-layouts.app title="Dashboard">
<div class="mb-6 flex flex-wrap items-end justify-between gap-4"><div><p class="text-sm text-slate-500">An overview of Combo Package activity and subscriptions.</p><div class="mt-3 flex flex-wrap items-center gap-2"><label for="dashboard-date-mode" class="text-sm font-medium text-slate-700">Date:</label><select id="dashboard-date-mode" class="rounded-md border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500"><option value="all" @selected(! $selectedDate)>All</option><option value="specific" @selected($selectedDate)>Specific Date</option></select><input id="dashboard-date" type="date" value="{{ $selectedDate?->toDateString() }}" class="rounded-md border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500 @if(! $selectedDate) hidden @endif"><button id="dashboard-date-reset" type="button" class="text-sm font-medium text-teal-700 hover:text-teal-800 @if(! $selectedDate) hidden @endif">Reset</button></div></div><p id="dashboard-last-updated" class="text-xs text-slate-400">Last updated: {{ now()->format('H:i:s') }}</p></div>
<div class="grid grid-cols-2 gap-3 lg:grid-cols-4 xl:grid-cols-8">@foreach($stats as $label=>$value)<x-stat-card :label="$label === \"Today's Purchases\" && $selectedDate ? 'Selected Date Purchases' : $label" :value="$value" :live-key="$label" :icon="str_contains($label,'Daily')?'sun':(str_contains($label,'Weekly')?'calendar-days':(str_contains($label,'Monthly')?'calendar':'users'))"/>@endforeach</div>
<div class="mt-6 grid gap-6 xl:grid-cols-2"><section class="rounded-lg border border-slate-200 bg-white p-5"><div class="mb-5 flex items-center justify-between"><h2 class="font-semibold">Purchases by package</h2><span id="dashboard-package-period" class="text-xs text-slate-500">{{ $selectedDate ? $selectedDate->format('d M Y') : 'All' }}</span></div><div id="dashboard-package-chart">@php($largestPackageCount=max(1,$packageCounts->max()))@foreach($packageCounts as $name=>$count)<div class="mb-5"><div class="mb-2 flex justify-between text-sm"><span>{{ $name }} Combo</span><span class="font-semibold">{{ number_format($count) }}</span></div><div class="h-2 rounded bg-slate-100"><div class="chart-bar h-2 rounded" style="width:{{ ($count/$largestPackageCount)*100 }}%"></div></div></div>@endforeach</div></section>
<section class="rounded-lg border border-slate-200 bg-white p-5"><div class="mb-5 flex items-center justify-between"><h2 class="font-semibold">Purchases over time</h2><span id="dashboard-trend-period" class="text-xs text-slate-500">{{ $selectedDate ? $selectedDate->format('d M Y') : 'Last 7 days' }}</span></div>@php($largestTrendCount=max(1,collect($purchaseTrend)->max('total')))<div id="dashboard-trend-chart" class="flex h-40 items-end justify-between gap-2">@foreach($purchaseTrend as $day)<div class="flex flex-1 flex-col items-center gap-2"><div class="w-full rounded-t bg-teal-500" style="height:{{ max(2,($day['total']/$largestTrendCount)*140) }}px"></div><span class="text-[10px] text-slate-500">{{ $day['label'] }}</span></div>@endforeach</div></section></div>
<section class="mt-6 overflow-hidden rounded-lg border border-slate-200 bg-white"><div class="flex items-center justify-between border-b border-slate-200 p-5"><div><h2 class="font-semibold">Recent purchases</h2><p class="mt-1 text-sm text-slate-500">Latest Combo Package transactions</p></div><a id="dashboard-view-all" class="text-sm font-medium text-teal-700" href="{{ route('combo-purchases.index', $selectedDate ? ['date' => $selectedDate->toDateString()] : []) }}">View all</a></div><div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">ID</th><th class="px-5 py-3">MSISDN</th><th class="px-5 py-3">Package</th><th class="px-5 py-3">Purchase date</th><th class="px-5 py-3">Expiry date</th><th class="px-5 py-3">Price</th><th class="px-5 py-3">Amount MB</th><th class="px-5 py-3">Status</th></tr></thead><tbody id="dashboard-recent-purchases">@forelse($recentPurchases as $purchase)<tr class="table-row border-t border-slate-100"><td class="px-5 py-4 font-medium">{{ $purchase->id }}</td><td class="px-5 py-4 text-slate-500">{{ $purchase->msisdn }}</td><td class="px-5 py-4">{{ $purchase->package }}</td><td class="px-5 py-4 text-slate-500">{{ \Carbon\Carbon::parse($purchase->purchase_date)->format('d M Y, H:i') }}</td><td class="px-5 py-4 text-slate-500">{{ \Carbon\Carbon::parse($purchase->expiry_date)->format('d M Y, H:i') }}</td><td class="px-5 py-4">{{ number_format($purchase->price,2) }}</td><td class="px-5 py-4">{{ number_format($purchase->amount_mb) }}</td><td class="px-5 py-4"><x-status :value="$purchase->status"/></td></tr>@empty<tr><td colspan="8" class="px-5 py-10 text-center text-slate-500">No Combo Package purchases found.</td></tr>@endforelse</tbody></table></div><div class="border-t border-slate-200 px-5 py-3">{{ $recentPurchases->links() }}</div></section>
<script>
(() => {
    if (window.shaafiDashboardPoller) clearInterval(window.shaafiDashboardPoller);
    let inFlight = false;
    let recentSignature = '';
    const lastUpdated = document.getElementById('dashboard-last-updated');
    const recentBody = document.getElementById('dashboard-recent-purchases');
    const dateMode = document.getElementById('dashboard-date-mode');
    const dateInput = document.getElementById('dashboard-date');
    const resetDate = document.getElementById('dashboard-date-reset');
    const packageChart = document.getElementById('dashboard-package-chart');
    const trendChart = document.getElementById('dashboard-trend-chart');
    const money = new Intl.NumberFormat(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});

    function cell(value, className = '') { const td = document.createElement('td'); td.className = `px-5 py-4 ${className}`; td.textContent = value; return td; }
    function activeDate() { return dateMode.value === 'specific' && dateInput.value ? dateInput.value : 'all'; }
    function renderPackageChart(counts) {
        const largest = Math.max(1, ...Object.values(counts).map(Number));
        packageChart.replaceChildren(...Object.entries(counts).map(([name, count]) => {
            const wrapper = document.createElement('div'); wrapper.className = 'mb-5';
            wrapper.innerHTML = `<div class="mb-2 flex justify-between text-sm"><span>${name} Combo</span><span class="font-semibold">${Number(count).toLocaleString()}</span></div><div class="h-2 rounded bg-slate-100"><div class="chart-bar h-2 rounded" style="width:${(Number(count) / largest) * 100}%"></div></div>`;
            return wrapper;
        }));
    }
    function renderTrendChart(trend) {
        const largest = Math.max(1, ...trend.map(day => Number(day.total)));
        trendChart.replaceChildren(...trend.map(day => {
            const item = document.createElement('div'); item.className = 'flex flex-1 flex-col items-center gap-2';
            item.innerHTML = `<div class="w-full rounded-t bg-teal-500" style="height:${Math.max(2, (Number(day.total) / largest) * 140)}px"></div><span class="text-[10px] text-slate-500">${day.label}</span>`;
            return item;
        }));
    }
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
            const query = new URLSearchParams({date: activeDate()});
            const response = await fetch(`{{ route('dashboard.live') }}?${query}`, {headers: {'Accept': 'application/json', 'Cache-Control': 'no-cache'}, credentials: 'same-origin', cache: 'no-store'});
            if (!response.ok) throw new Error('Live update failed');
            const data = await response.json();
            Object.entries(data.stats).forEach(([label, value]) => document.querySelectorAll('[data-dashboard-stat]').forEach(node => { if (node.dataset.dashboardStat === label && node.textContent !== String(value)) node.textContent = Number(value).toLocaleString(); }));
            document.querySelector('[data-dashboard-stat-label="Today\'s Purchases"]').textContent = activeDate() === 'all' ? "Today's Purchases" : 'Selected Date Purchases';
            renderRecent(data.recent_purchases);
            renderPackageChart(data.package_counts);
            renderTrendChart(data.purchase_trend);
            lastUpdated.textContent = `Last updated: ${data.last_updated}`;
        } catch (error) {
            console.error('Dashboard live update failed.', error);
        } finally { inFlight = false; }
    }
    function applyDateFilter() {
        const specific = dateMode.value === 'specific';
        dateInput.classList.toggle('hidden', !specific);
        resetDate.classList.toggle('hidden', !specific);
        if (specific && !dateInput.value) dateInput.value = new Date().toISOString().slice(0, 10);
        document.getElementById('dashboard-package-period').textContent = specific ? dateInput.value : 'All';
        document.getElementById('dashboard-trend-period').textContent = specific ? dateInput.value : 'Last 7 days';
        refreshDashboard();
    }
    dateMode.addEventListener('change', applyDateFilter);
    dateInput.addEventListener('change', () => { if (dateInput.value) applyDateFilter(); });
    resetDate.addEventListener('click', () => { dateMode.value = 'all'; dateInput.value = ''; applyDateFilter(); });
    refreshDashboard();
    window.shaafiDashboardPoller = setInterval(refreshDashboard, 1000);
    window.addEventListener('beforeunload', () => clearInterval(window.shaafiDashboardPoller), {once: true});
})();
</script>
</x-layouts.app>
