<x-layouts.app title="Dashboard">
@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
<style>
/* ---- Dashboard-only theme (scoped to body.dash-ui, set on the dashboard route only) ---- */
.dash-ui{
    --bg:#f3f5f7;--surface:#ffffff;--border:#e4e8ec;--border-soft:#eef1f4;--text:#111827;--muted:#6b7280;--track:#eef2f4;--row-hover:#f8fafb;
    --teal:#0e8f7e;--teal-soft:#e3f3f0;--blue:#3b63d9;--blue-soft:#e9eefc;--orange:#e0761f;--green:#16924f;--green-soft:#e4f5eb;
    --nav:#0f1b2e;--nav-hover:#1a2940;--nav-text:#a9b4c4;--nav-active:#0e8f7e;
    font-family:'DM Sans',ui-sans-serif,system-ui,sans-serif;background:var(--bg);color:var(--text);color-scheme:light;
}
@media (prefers-color-scheme:dark){
    .dash-ui{
        --bg:#0b1120;--surface:#121a2a;--border:#222d40;--border-soft:#1b2537;--text:#e5eaf1;--muted:#8d99ab;--track:#1d2739;--row-hover:#162034;
        --teal:#2fb5a2;--teal-soft:rgba(47,181,162,.15);--blue:#7593f2;--blue-soft:rgba(117,147,242,.15);--orange:#f29a4f;--green:#3fcb85;--green-soft:rgba(63,203,133,.15);
        --nav:#080e1a;--nav-hover:#141e31;--nav-text:#94a0b2;
        color-scheme:dark;
    }
}

/* Sidebar */
.dash-ui .app-sidebar{background:var(--nav);color:var(--nav-text)}
.dash-ui .app-nav a{color:var(--nav-text)}
.dash-ui .app-nav a:not(.nav-active):hover{background:var(--nav-hover);color:#fff}
.dash-ui .app-nav a.nav-active{background:var(--nav-active);color:#fff}
.dash-ui .app-sidebar-foot{border-color:rgba(255,255,255,.08)}

/* Top bar */
.dash-topbar{display:flex;align-items:center;justify-content:flex-end;gap:14px;height:56px;padding:0 28px;background:var(--surface);border-bottom:1px solid var(--border)}
.dash-icon-btn{display:grid;place-items:center;width:36px;height:36px;border-radius:10px;color:var(--muted)}
.dash-icon-btn:hover{background:var(--track);color:var(--text)}
.dash-user{display:flex;align-items:center;gap:10px;padding-left:14px;border-left:1px solid var(--border)}
.dash-avatar{display:grid;place-items:center;width:32px;height:32px;border-radius:999px;background:var(--teal-soft);color:var(--teal);font-size:12px;font-weight:700}
.dash-user-name{font-size:13px;font-weight:600;line-height:1.2}
.dash-user-role{font-size:12px;color:var(--muted);line-height:1.2}
.dash-logout{display:inline-flex;align-items:center;gap:6px;height:32px;padding:0 12px;border:1px solid var(--border);border-radius:9px;font-size:13px;font-weight:500;color:var(--muted)}
.dash-logout:hover{color:var(--teal);border-color:var(--teal)}

/* Page */
.dash .hidden{display:none!important}
.dash-head{display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:22px}
.dash-title{font-size:26px;font-weight:700;letter-spacing:-.02em;line-height:1.15}
.dash-sub{margin-top:4px;font-size:14px;color:var(--muted)}
.dash-filter-wrap{display:flex;flex-direction:column;align-items:flex-end;gap:6px}
.dash-filter{display:flex;flex-wrap:wrap;align-items:center;justify-content:flex-end;gap:8px;font-size:13px}
.dash-filter label{color:var(--muted);font-weight:500}
.dash-range{display:inline-flex;flex-wrap:wrap;align-items:center;gap:8px}
.dash-control{height:36px;padding:0 10px;border:1px solid var(--border);border-radius:9px;background:var(--surface);color:var(--text);font-size:13px;outline:none}
.dash-control:focus{border-color:var(--teal);box-shadow:0 0 0 3px var(--teal-soft)}
select.dash-control{padding-right:28px}
.dash-btn{height:36px;padding:0 16px;border-radius:9px;background:#0e8f7e;color:#fff;font-size:13px;font-weight:600}
.dash-btn:hover{background:#0b7a6b}
.dash-btn:disabled{opacity:.6;cursor:wait}
.dash-loading{display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--muted)}
.dash-spinner{width:12px;height:12px;border-radius:999px;border:2px solid var(--border);border-top-color:var(--teal);animation:dash-spin .8s linear infinite}
@keyframes dash-spin{to{transform:rotate(360deg)}}
.dash-updated{font-size:12px;color:var(--muted)}

/* Cards */
.dash-card{background:var(--surface);border:1px solid var(--border);border-radius:14px}
.dash-card-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:18px 20px 0}
.dash-card-title{display:flex;align-items:center;gap:10px;font-size:15px;font-weight:600}
.dash-card-meta{font-size:12px;color:var(--muted)}
.dash-card-body{padding:18px 20px 20px}

/* KPI cards */
.kpi-grid{display:grid;gap:16px;grid-template-columns:1fr}
@media (min-width:1200px){.kpi-grid{grid-template-columns:3fr 5fr}}
.kpi-card{border-top-width:3px}
.kpi-card--teal{border-top-color:var(--teal)}
.kpi-card--blue{border-top-color:var(--blue)}
.kpi-icon{display:grid;place-items:center;width:30px;height:30px;border-radius:8px}
.kpi-card--teal .kpi-icon{background:var(--teal-soft);color:var(--teal)}
.kpi-card--blue .kpi-icon{background:var(--blue-soft);color:var(--blue)}
.kpi-row{display:flex;padding:16px 0 20px}
.kpi{flex:1 1 0;min-width:0;padding:0 20px}
.kpi + .kpi{border-left:1px solid var(--border)}
.kpi-label{font-size:12px;font-weight:500;color:var(--muted);line-height:1.3}
.kpi-value{margin-top:6px;font-size:28px;font-weight:700;letter-spacing:-.02em;line-height:1.1;font-variant-numeric:tabular-nums}
.tone-teal{color:var(--teal)}.tone-green{color:var(--green)}.tone-orange{color:var(--orange)}.tone-blue{color:var(--blue)}

/* Charts */
.chart-grid{display:grid;gap:16px;grid-template-columns:1fr;margin-top:16px}
@media (min-width:1024px){.chart-grid{grid-template-columns:1fr 1fr}}
.pkg + .pkg{margin-top:18px}
.pkg-line{display:flex;justify-content:space-between;margin-bottom:8px;font-size:14px}
.pkg-count{font-weight:600;font-variant-numeric:tabular-nums}
.pkg-track{height:8px;border-radius:999px;background:var(--track);overflow:hidden}
.pkg-fill{height:100%;border-radius:999px;background:var(--teal);transition:width .4s ease}
.trend{display:flex;align-items:flex-end;gap:10px;height:170px}
.trend-col{flex:1 1 0;min-width:0;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;gap:8px;height:100%}
.trend-bar{width:100%;max-width:44px;border-radius:6px 6px 2px 2px;background:var(--teal);transition:height .4s ease}
.trend-col:hover .trend-bar{opacity:.8}
.trend-label{height:14px;font-size:11px;color:var(--muted);white-space:nowrap}

/* Recent purchases */
.recent{margin-top:16px;overflow:hidden}
.recent .dash-card-head{padding-bottom:16px;border-bottom:1px solid var(--border)}
.recent-sub{margin-top:3px;font-size:13px;color:var(--muted)}
.dash-link{font-size:13px;font-weight:600;color:var(--teal)}
.dash-link:hover{text-decoration:underline}
.dash-table{width:100%;min-width:860px;border-collapse:collapse;font-size:14px;text-align:left}
.dash-table th{padding:11px 20px;background:var(--row-hover);font-size:11px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:var(--muted)}
.dash-table td{padding:14px 20px;border-top:1px solid var(--border-soft);white-space:nowrap}
.dash-table tbody tr:hover{background:var(--row-hover)}
.dash-table .muted{color:var(--muted)}
.dash-table .strong{font-weight:600}
.dash-table .num{font-variant-numeric:tabular-nums}
.dash-empty{padding:40px 20px!important;text-align:center;color:var(--muted)}
.pill{display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:600;background:var(--track);color:var(--muted)}
.pill::before{content:'';width:6px;height:6px;border-radius:999px;background:currentColor}
.pill-active{background:var(--green-soft);color:var(--green)}
.recent-foot{padding:12px 20px;border-top:1px solid var(--border)}
.recent-foot a,.recent-foot span{background:var(--surface)!important;color:var(--text)!important;border-color:var(--border)!important;border-radius:9px!important}
.recent-foot span[aria-disabled],.recent-foot span.cursor-default{color:var(--muted)!important}

/* Mobile: horizontal scrolling menu, stacked KPIs */
@media (max-width:1023px){
    .dash-ui .app-sidebar{position:sticky;top:0;transform:none!important;width:auto;padding:12px 16px 0;z-index:30}
    .dash-ui .app-brand{margin-bottom:10px;padding:0}
    .dash-ui .app-nav{display:flex;gap:6px;overflow-x:auto;padding-bottom:12px;scrollbar-width:none}
    .dash-ui .app-nav::-webkit-scrollbar{display:none}
    .dash-ui .app-nav > a{flex:0 0 auto;margin:0!important;white-space:nowrap;padding:8px 12px}
    .dash-ui .app-sidebar-foot{display:none}
    .dash-topbar{padding:0 16px}
}
@media (max-width:640px){
    .dash-user-text{display:none}
    .dash-logout span{display:none}
    .dash-filter-wrap{align-items:flex-start;width:100%}
    .dash-filter{justify-content:flex-start}
    .kpi-row{flex-direction:column;padding:8px 0 12px}
    .kpi{padding:12px 20px}
    .kpi + .kpi{border-left:0;border-top:1px solid var(--border)}
}
</style>
@endpush

@php
    $subscriberKpis = ['Total Subscribers' => 'teal', 'Active Subscribers' => 'green', 'Expired Subscribers' => 'orange'];
    $purchaseKpis = ['Total Combo Purchases' => 'blue', 'Daily Purchases' => null, 'Weekly Purchases' => null, 'Monthly Purchases' => null, "Today's Purchases" => 'green'];
    $periodLabel = $fromDate ? $fromDate->format('d M Y').' - '.$toDate->copy()->subDay()->format('d M Y') : null;
@endphp

<div class="dash">
    <div class="dash-head">
        <div>
            <h1 class="dash-title">Dashboard</h1>
            <p class="dash-sub">An overview of Combo Package activity and subscriptions.</p>
        </div>
        <div class="dash-filter-wrap">
            <div class="dash-filter">
                <label for="dashboard-date-mode">Date</label>
                <select id="dashboard-date-mode" class="dash-control">
                    <option value="all" @selected(! $fromDate)>All</option>
                    <option value="today">Today</option>
                    <option value="7">Last 7 days</option>
                    <option value="30">Last 30 days</option>
                    <option value="range" @selected($fromDate)>Custom range</option>
                </select>
                <span id="dashboard-range-fields" class="dash-range @if(! $fromDate) hidden @endif">
                    <label for="dashboard-from-date">From</label>
                    <input id="dashboard-from-date" type="date" value="{{ $fromDate?->toDateString() }}" class="dash-control">
                    <label for="dashboard-to-date">To</label>
                    <input id="dashboard-to-date" type="date" value="{{ $toDate?->copy()->subDay()->toDateString() }}" class="dash-control">
                </span>
                <button id="dashboard-date-apply" type="button" class="dash-btn">Apply</button>
                <span id="dashboard-filter-loading" class="dash-loading hidden" role="status"><span class="dash-spinner"></span>Loading…</span>
            </div>
            <p id="dashboard-last-updated" class="dash-updated">Last updated: {{ now()->format('d M Y, H:i:s') }}</p>
        </div>
    </div>

    <div class="kpi-grid">
        <section class="dash-card kpi-card kpi-card--teal">
            <div class="dash-card-head"><h2 class="dash-card-title"><span class="kpi-icon"><i data-lucide="users" class="h-4 w-4"></i></span>Subscribers</h2></div>
            <div class="kpi-row">
                @foreach($subscriberKpis as $label => $tone)
                    <div class="kpi"><p class="kpi-label">{{ $label }}</p><p class="kpi-value tone-{{ $tone }}" data-dashboard-stat="{{ $label }}">{{ number_format($stats[$label] ?? 0) }}</p></div>
                @endforeach
            </div>
        </section>
        <section class="dash-card kpi-card kpi-card--blue">
            <div class="dash-card-head"><h2 class="dash-card-title"><span class="kpi-icon"><i data-lucide="shopping-bag" class="h-4 w-4"></i></span>Purchases</h2></div>
            <div class="kpi-row">
                @foreach($purchaseKpis as $label => $tone)
                    <div class="kpi"><p class="kpi-label">{{ $label }}</p><p class="kpi-value {{ $tone ? 'tone-'.$tone : '' }}" data-dashboard-stat="{{ $label }}">{{ number_format($stats[$label] ?? 0) }}</p></div>
                @endforeach
            </div>
        </section>
    </div>

    <div class="chart-grid">
        <section class="dash-card">
            <div class="dash-card-head"><h2 class="dash-card-title">Purchases by package</h2><span id="dashboard-package-period" class="dash-card-meta">{{ $periodLabel ?? 'All' }}</span></div>
            <div id="dashboard-package-chart" class="dash-card-body">
                @php $largestPackageCount = max(1, $packageCounts->max()); @endphp
                @foreach($packageCounts as $name => $count)
                    <div class="pkg"><div class="pkg-line"><span>{{ $name }} Combo</span><span class="pkg-count">{{ number_format($count) }}</span></div><div class="pkg-track"><div class="pkg-fill" style="width:{{ ($count / $largestPackageCount) * 100 }}%"></div></div></div>
                @endforeach
            </div>
        </section>
        <section class="dash-card">
            <div class="dash-card-head"><h2 class="dash-card-title">Purchases over time</h2><span id="dashboard-trend-period" class="dash-card-meta">{{ $periodLabel ?? 'Last 7 days' }}</span></div>
            <div class="dash-card-body">
                @php $largestTrendCount = max(1, collect($purchaseTrend)->max('total')); @endphp
                @php $trendLabelStep = max(1, (int) ceil(count($purchaseTrend) / 10)); @endphp
                <div id="dashboard-trend-chart" class="trend">
                    @foreach($purchaseTrend as $i => $day)
                        <div class="trend-col" title="{{ $day['label'] }}: {{ number_format($day['total']) }}"><div class="trend-bar" style="height:{{ max(2, ($day['total'] / $largestTrendCount) * 140) }}px"></div><span class="trend-label">{{ $i % $trendLabelStep === 0 ? $day['label'] : '' }}</span></div>
                    @endforeach
                </div>
            </div>
        </section>
    </div>

    <section class="dash-card recent">
        <div class="dash-card-head">
            <div><h2 class="dash-card-title">Recent purchases</h2><p class="recent-sub">Latest Combo Package transactions</p></div>
            <a id="dashboard-view-all" class="dash-link" href="{{ route('combo-purchases.index') }}">View all</a>
        </div>
        <div class="overflow-x-auto">
            <table class="dash-table">
                <thead><tr><th>ID</th><th>MSISDN</th><th>Package</th><th>Purchase date</th><th>Expiry date</th><th>Price</th><th>Amount MB</th><th>Status</th></tr></thead>
                <tbody id="dashboard-recent-purchases">
                    @forelse($recentPurchases as $purchase)
                        <tr>
                            <td class="strong">{{ $purchase->id }}</td>
                            <td class="muted">{{ $purchase->msisdn }}</td>
                            <td>{{ $purchase->package }}</td>
                            <td class="muted">{{ \Carbon\Carbon::parse($purchase->purchase_date)->format('d M Y, H:i') }}</td>
                            <td class="muted">{{ \Carbon\Carbon::parse($purchase->expiry_date)->format('d M Y, H:i') }}</td>
                            <td class="num">{{ number_format($purchase->price, 2) }}</td>
                            <td class="num">{{ number_format($purchase->amount_mb) }}</td>
                            <td><span class="pill {{ in_array($purchase->status, ['Active', 'Completed'], true) ? 'pill-active' : '' }}">{{ $purchase->status }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="dash-empty">No Combo Package purchases found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="recent-foot">{{ $recentPurchases->links() }}</div>
    </section>
</div>

<script>
(() => {
    if (window.shaafiDashboardPoller) clearInterval(window.shaafiDashboardPoller);
    let inFlight = false;
    let refreshPending = false;
    let recentSignature = '';
    const lastUpdated = document.getElementById('dashboard-last-updated');
    const recentBody = document.getElementById('dashboard-recent-purchases');
    const dateMode = document.getElementById('dashboard-date-mode');
    const rangeFields = document.getElementById('dashboard-range-fields');
    const fromDate = document.getElementById('dashboard-from-date');
    const toDate = document.getElementById('dashboard-to-date');
    const applyDate = document.getElementById('dashboard-date-apply');
    const filterLoading = document.getElementById('dashboard-filter-loading');
    const packageChart = document.getElementById('dashboard-package-chart');
    const trendChart = document.getElementById('dashboard-trend-chart');
    const money = new Intl.NumberFormat(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const presetDays = {today: 0, '7': 6, '30': 29};
    const presetLabels = {today: 'Today', '7': 'Last 7 days', '30': 'Last 30 days'};

    function escapeHtml(value) { return String(value).replace(/[&<>"']/g, ch => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[ch])); }
    function isoDate(date) { return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`; }
    function presetRange(mode) {
        if (!(mode in presetDays)) return null;
        const end = new Date();
        const start = new Date();
        start.setDate(end.getDate() - presetDays[mode]);
        return {from: isoDate(start), to: isoDate(end)};
    }
    function cell(value, className = '') { const td = document.createElement('td'); td.className = className; td.textContent = value; return td; }
    let appliedRange = dateMode.value === 'range' && fromDate.value && toDate.value ? {from: fromDate.value, to: toDate.value} : null;
    if (appliedRange) {
        const preset = Object.keys(presetDays).find(mode => { const r = presetRange(mode); return r.from === appliedRange.from && r.to === appliedRange.to; });
        if (preset) { dateMode.value = preset; rangeFields.classList.add('hidden'); }
    }
    function appliedRangeKey() { return appliedRange ? `${appliedRange.from}:${appliedRange.to}` : 'all'; }
    function setFilterLoading(loading) {
        filterLoading.classList.toggle('hidden', !loading);
        applyDate.disabled = loading;
    }
    function renderPackageChart(counts) {
        const largest = Math.max(1, ...Object.values(counts).map(Number));
        packageChart.replaceChildren(...Object.entries(counts).map(([name, count]) => {
            const wrapper = document.createElement('div'); wrapper.className = 'pkg';
            wrapper.innerHTML = `<div class="pkg-line"><span>${escapeHtml(name)} Combo</span><span class="pkg-count">${Number(count).toLocaleString()}</span></div><div class="pkg-track"><div class="pkg-fill" style="width:${(Number(count) / largest) * 100}%"></div></div>`;
            return wrapper;
        }));
    }
    function renderTrendChart(trend) {
        const largest = Math.max(1, ...trend.map(day => Number(day.total)));
        const step = Math.max(1, Math.ceil(trend.length / 10));
        trendChart.replaceChildren(...trend.map((day, i) => {
            const item = document.createElement('div'); item.className = 'trend-col';
            item.title = `${day.label}: ${Number(day.total).toLocaleString()}`;
            item.innerHTML = `<div class="trend-bar" style="height:${Math.max(2, (Number(day.total) / largest) * 140)}px"></div><span class="trend-label">${i % step === 0 ? escapeHtml(day.label) : ''}</span>`;
            return item;
        }));
    }
    function renderRecent(purchases) {
        const signature = JSON.stringify(purchases);
        if (signature === recentSignature) return;
        recentSignature = signature;
        recentBody.replaceChildren();
        if (!purchases.length) {
            const row = document.createElement('tr'); const td = cell('No Combo Package purchases found.', 'dash-empty'); td.colSpan = 8; row.append(td); recentBody.append(row);
            return;
        }
        purchases.forEach(purchase => {
            const row = document.createElement('tr');
            row.append(cell(purchase.id, 'strong'), cell(purchase.msisdn, 'muted'), cell(purchase.package), cell(purchase.purchase_date, 'muted'), cell(purchase.expiry_date, 'muted'), cell(money.format(purchase.price), 'num'), cell(Number(purchase.amount_mb).toLocaleString(), 'num'));
            const status = document.createElement('span'); status.className = purchase.status === 'Active' || purchase.status === 'Completed' ? 'pill pill-active' : 'pill'; status.textContent = purchase.status;
            const statusCell = cell(''); statusCell.append(status); row.append(statusCell); recentBody.append(row);
        });
    }
    async function refreshDashboard() {
        if (inFlight) {
            refreshPending = true;
            return;
        }
        inFlight = true;
        const requestedRangeKey = appliedRangeKey();
        try {
            const query = new URLSearchParams(appliedRange || {});
            const response = await fetch(`{{ route('dashboard.live') }}?${query}`, {headers: {'Accept': 'application/json', 'Cache-Control': 'no-cache'}, credentials: 'same-origin', cache: 'no-store'});
            if (!response.ok) throw new Error('Live update failed');
            const data = await response.json();
            if (requestedRangeKey !== appliedRangeKey()) return;
            Object.entries(data.stats).forEach(([label, value]) => document.querySelectorAll('[data-dashboard-stat]').forEach(node => { if (node.dataset.dashboardStat === label) { const text = Number(value).toLocaleString(); if (node.textContent !== text) node.textContent = text; } }));
            renderRecent(data.recent_purchases);
            renderPackageChart(data.package_counts);
            renderTrendChart(data.purchase_trend);
            lastUpdated.textContent = `Last updated: ${data.last_updated}`;
            setFilterLoading(false);
        } catch (error) {
            console.error('Dashboard live update failed.', error);
            if (requestedRangeKey === appliedRangeKey()) setFilterLoading(false);
        } finally {
            inFlight = false;
            if (refreshPending) {
                refreshPending = false;
                refreshDashboard();
            }
        }
    }
    function toggleRangeInputs() {
        rangeFields.classList.toggle('hidden', dateMode.value !== 'range');
    }
    function applyDateFilter() {
        setFilterLoading(true);
        const mode = dateMode.value;
        if (mode === 'all') {
            appliedRange = null;
        } else if (mode in presetDays) {
            appliedRange = presetRange(mode);
        } else {
            if (!fromDate.value || !toDate.value || toDate.value < fromDate.value) {
                setFilterLoading(false);
                return;
            }
            appliedRange = {from: fromDate.value, to: toDate.value};
        }
        const label = !appliedRange ? 'All' : (presetLabels[mode] || `${appliedRange.from} - ${appliedRange.to}`);
        document.getElementById('dashboard-package-period').textContent = label;
        document.getElementById('dashboard-trend-period').textContent = appliedRange ? label : 'Last 7 days';
        refreshDashboard();
    }
    dateMode.addEventListener('change', toggleRangeInputs);
    applyDate.addEventListener('click', applyDateFilter);
    refreshDashboard();
    window.shaafiDashboardPoller = setInterval(refreshDashboard, 1000);
    window.addEventListener('beforeunload', () => clearInterval(window.shaafiDashboardPoller), {once: true});
})();
</script>
</x-layouts.app>
