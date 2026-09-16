<x-layouts.app title="Settings">
    <div class="max-w-6xl space-y-5">
        @if(session('success')) <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div> @endif

        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <div class="mb-5"><h2 class="font-semibold">Create Agent User</h2><p class="mt-1 text-sm text-slate-500">New users are always created as Agents.</p></div>
            <form method="POST" action="{{ route('settings.users.store') }}" class="grid gap-4 sm:grid-cols-2">
                @csrf
                <label class="text-sm"><span class="mb-1 block text-slate-600">First Name</span><input name="firstName" value="{{ old('firstName') }}" required class="w-full rounded-md border border-slate-300 p-2"><x-auth-error field="firstName" /></label>
                <label class="text-sm"><span class="mb-1 block text-slate-600">Last Name</span><input name="last_name" value="{{ old('last_name') }}" required class="w-full rounded-md border border-slate-300 p-2"><x-auth-error field="last_name" /></label>
                <label class="text-sm"><span class="mb-1 block text-slate-600">Phone Number</span><input name="number" value="{{ old('number') }}" class="w-full rounded-md border border-slate-300 p-2"><x-auth-error field="number" /></label>
                <label class="text-sm"><span class="mb-1 block text-slate-600">Username</span><input name="username" value="{{ old('username') }}" required autocomplete="username" class="w-full rounded-md border border-slate-300 p-2"><x-auth-error field="username" /></label>
                <label class="text-sm"><span class="mb-1 block text-slate-600">Password</span><input type="password" name="password" required autocomplete="new-password" class="w-full rounded-md border border-slate-300 p-2"><x-auth-error field="password" /></label>
                <label class="text-sm"><span class="mb-1 block text-slate-600">Confirm Password</span><input type="password" name="password_confirmation" required autocomplete="new-password" class="w-full rounded-md border border-slate-300 p-2"></label>
                <label class="text-sm"><span class="mb-1 block text-slate-600">Status</span><select name="status" required class="w-full rounded-md border border-slate-300 p-2"><option value="ACTIVE" @selected(old('status') === 'ACTIVE')>ACTIVE</option><option value="INIT" @selected(old('status') === 'INIT')>INIT</option></select><x-auth-error field="status" /></label>
                <div class="flex items-end"><button class="rounded-md bg-teal-700 px-4 py-2 text-sm font-medium text-white">Create Agent</button></div>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <div class="border-b border-slate-200 p-5"><h2 class="font-semibold">Portal Users</h2><p class="mt-1 text-sm text-slate-500">Only Agent account statuses can be managed here.</p></div>
            <div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-slate-50 text-slate-500"><tr><th class="px-5 py-3 font-medium">Name</th><th class="px-5 py-3 font-medium">Username</th><th class="px-5 py-3 font-medium">Role</th><th class="px-5 py-3 font-medium">Status</th><th class="px-5 py-3 font-medium">Action</th></tr></thead><tbody class="divide-y divide-slate-100">@foreach($users as $user)<tr><td class="px-5 py-3"><p class="font-medium">{{ $user->displayName() }}</p><p class="text-xs text-slate-500">{{ $user->number }}</p></td><td class="px-5 py-3">{{ $user->username }}</td><td class="px-5 py-3">{{ $user->roleLabel() }}</td><td class="px-5 py-3">{{ $user->status }}</td><td class="px-5 py-3">@if($user->isSuperAdmin()) <span class="text-xs text-slate-500">Protected</span> @else <form method="POST" action="{{ route('settings.users.status', $user) }}" class="flex gap-2">@csrf @method('PATCH')<select name="status" class="rounded border border-slate-300 px-2 py-1 text-xs"><option value="ACTIVE" @selected($user->status === 'ACTIVE')>ACTIVE</option><option value="INIT" @selected($user->status === 'INIT')>INIT</option><option value="DELETED" @selected($user->status === 'DELETED')>DELETED</option></select><button class="text-xs font-medium text-teal-700">Save</button></form> @endif</td></tr>@endforeach</tbody></table></div>
        </section>
    </div>
</x-layouts.app>
