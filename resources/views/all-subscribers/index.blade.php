<x-layouts.app title="All Subscribers">
    <style>[x-cloak]{display:none!important}</style>
    <div x-data="allSubscriberTable()">
        <div class="mb-6"><p class="text-sm text-slate-500">Complete Combo subscriber history, including active and expired subscriptions.</p></div>
        <form class="mb-5 flex flex-col gap-3 sm:flex-row" method="GET">
            <input name="q" value="{{ request('q') }}" placeholder="Search subscriber number" class="rounded-md border border-slate-300 p-2 text-sm">
            <select name="package" class="rounded-md border border-slate-300 p-2 text-sm"><option value="">All package tiers</option>@foreach(['Daily', 'Weekly', 'Monthly'] as $package)<option value="{{ $package }}" @selected(request('package') === $package)>{{ $package }}</option>@endforeach</select>
            <button class="rounded-md bg-teal-700 px-4 py-2 text-white">Filter</button>
        </form>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white"><div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr>@foreach(['ID','MSISDN','Package','Purchase date','Expiry date','Price','Subscription status','Action','Done by'] as $heading)<th class="whitespace-nowrap px-4 py-3">{{ $heading }}</th>@endforeach</tr></thead><tbody>
            @forelse($records as $subscriber)
                <tr class="border-t" data-subscriber-action="{{ $subscriber->id }}">
                    <td class="px-4 py-4">#{{ $subscriber->id }}</td><td class="px-4 py-4">{{ $subscriber->msisdn }}</td><td class="px-4 py-4">{{ $subscriber->package }}</td><td class="whitespace-nowrap px-4 py-4">{{ \Carbon\Carbon::parse($subscriber->purchase_date)->format('d M Y, H:i') }}</td><td class="whitespace-nowrap px-4 py-4">{{ \Carbon\Carbon::parse($subscriber->expiry_date)->format('d M Y, H:i') }}</td><td class="px-4 py-4">{{ number_format($subscriber->price, 2) }}</td><td class="px-4 py-4"><x-status :value="$subscriber->status" /></td>
                    <td class="px-4 py-4" data-action>@if(optional($subscriber->action)->agent_status === 'COMPLETED')<button disabled class="cursor-not-allowed rounded-md bg-emerald-100 px-3 py-1.5 text-xs font-semibold text-emerald-700">Completed</button>@else<button @click="openConfirm({{ $subscriber->id }})" class="rounded-md bg-amber-100 px-3 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-200">Pending</button>@endif</td>
                    <td class="whitespace-nowrap px-4 py-4" data-done-by>{{ optional(optional($subscriber->action)->doneBy)->displayName() ?: '—' }}</td>
                </tr>
            @empty<tr><td colspan="9" class="p-8 text-center text-slate-500">No subscribers found.</td></tr>@endforelse
        </tbody></table></div><div class="border-t px-4 py-3">{{ $records->links() }}</div></section>

        <div x-cloak x-show="selectedId" class="fixed inset-0 z-50 grid place-items-center bg-slate-900/50 p-4" @keydown.escape.window="closeConfirm()"><div @click.outside="closeConfirm()" class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl"><h2 class="text-lg font-semibold">Complete subscriber</h2><p class="mt-2 text-sm text-slate-600">Are you sure you have completed the work for this subscriber?</p><p x-show="error" x-text="error" class="mt-3 text-sm text-rose-600"></p><div class="mt-6 flex justify-end gap-3"><button @click="closeConfirm()" :disabled="saving" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Cancel</button><button @click="confirm()" :disabled="saving" class="rounded-md bg-teal-700 px-4 py-2 text-sm font-medium text-white disabled:opacity-60"><span x-text="saving ? 'Saving…' : 'Yes, Confirm'"></span></button></div></div></div>
    </div>
    <script>
        function allSubscriberTable() { return { selectedId: null, saving: false, error: '', openConfirm(id) { this.selectedId = id; this.error = ''; }, closeConfirm() { if (!this.saving) { this.selectedId = null; this.error = ''; } }, async confirm() { this.saving = true; this.error = ''; try { const response = await fetch('{{ url('/all-subscribers') }}/' + this.selectedId + '/complete', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' } }); const data = await response.json(); if (!response.ok) throw new Error(data.message || 'Unable to complete this subscriber.'); const row = document.querySelector('[data-subscriber-action="' + this.selectedId + '"]'); row.querySelector('[data-action]').innerHTML = '<button disabled class="cursor-not-allowed rounded-md bg-emerald-100 px-3 py-1.5 text-xs font-semibold text-emerald-700">Completed</button>'; row.querySelector('[data-done-by]').textContent = data.done_by; this.selectedId = null; } catch (error) { this.error = error.message; } finally { this.saving = false; } } } }
    </script>
</x-layouts.app>
