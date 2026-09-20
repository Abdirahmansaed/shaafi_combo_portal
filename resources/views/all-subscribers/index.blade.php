<x-layouts.app title="All Subscribers">
    <div>
        <div class="mb-6"><p class="text-sm text-slate-500">Complete Combo subscriber history, including active and expired subscriptions.</p></div>
        <form class="mb-5 flex flex-col gap-3 sm:flex-row" method="GET">
            <input name="q" value="{{ request('q') }}" placeholder="Search subscriber number" class="rounded-md border border-slate-300 p-2 text-sm">
            <select name="package" class="rounded-md border border-slate-300 p-2 text-sm"><option value="">All package tiers</option>@foreach(['Daily', 'Weekly', 'Monthly'] as $package)<option value="{{ $package }}" @selected(request('package') === $package)>{{ $package }}</option>@endforeach</select>
            <button class="rounded-md bg-teal-700 px-4 py-2 text-white">Filter</button>
        </form>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white"><div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr>@foreach(['ID','MSISDN','Package','Purchase date','Expiry date','Price','Subscription status','Action','Done by'] as $heading)<th class="whitespace-nowrap px-4 py-3">{{ $heading }}</th>@endforeach</tr></thead><tbody>
            @forelse($records as $subscriber)
                <tr class="border-t">
                    <td class="px-4 py-4">#{{ $subscriber->id }}</td><td class="px-4 py-4">{{ $subscriber->msisdn }}</td><td class="px-4 py-4">{{ $subscriber->package }}</td><td class="whitespace-nowrap px-4 py-4">{{ \Carbon\Carbon::parse($subscriber->purchase_date)->format('d M Y, H:i') }}</td><td class="whitespace-nowrap px-4 py-4">{{ \Carbon\Carbon::parse($subscriber->expiry_date)->format('d M Y, H:i') }}</td><td class="px-4 py-4">{{ number_format($subscriber->price, 2) }}</td><td class="px-4 py-4"><x-status :value="$subscriber->status" /></td>
                    <td class="px-4 py-4">@if(optional($subscriber->action)->agent_status === 'COMPLETED')<button disabled class="cursor-not-allowed rounded-md bg-emerald-100 px-3 py-1.5 text-xs font-semibold text-emerald-700">Completed</button>@else<button disabled class="cursor-not-allowed rounded-md bg-amber-100 px-3 py-1.5 text-xs font-semibold text-amber-800">Pending</button>@endif</td>
                    <td class="whitespace-nowrap px-4 py-4">{{ optional(optional($subscriber->action)->doneBy)->displayName() ?: '—' }}</td>
                </tr>
            @empty<tr><td colspan="9" class="p-8 text-center text-slate-500">No subscribers found.</td></tr>@endforelse
        </tbody></table></div><div class="border-t px-4 py-3">{{ $records->links() }}</div></section>
    </div>
</x-layouts.app>
