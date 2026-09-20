<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'shaafi Combo portal' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/shaafi-logo.png') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>body{font-family:Inter,ui-sans-serif,system-ui,sans-serif}.nav-active{background:#0f766e;color:#fff}.chart-bar{background:#14b8a6}.table-row:hover{background:#f8fafc}</style>
</head>
<body class="bg-slate-50 text-slate-800" x-data="{ sidebar:false }">
<div class="min-h-screen lg:flex">
    <aside :class="sidebar ? 'translate-x-0' : '-translate-x-full'" class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-slate-900 px-4 py-5 text-slate-300 transition-transform lg:static lg:translate-x-0">
        <div class="mb-9 flex items-center gap-3 px-2"><img src="{{ asset('images/shaafi-portal-logo.svg') }}" alt="shaafi Combo portal" class="h-9 w-9 rounded-lg bg-white object-contain"><div><p class="font-semibold text-white">shaafi Combo portal</p><p class="text-xs text-slate-400">Management console</p></div></div>
        <nav class="space-y-1 text-sm font-medium">
            @php
                $links = [['dashboard','Dashboard','layout-dashboard'],['active-subscribers.index','Active Subscribers','user-check'],['all-subscribers.index','All Subscribers','users'],['combo-purchases.index','Combo Purchases','receipt-text']];
                if (auth()->user()->isSuperAdmin()) {
                    $links[] = ['reports.index','Reports','bar-chart-3'];
                    $links[] = ['settings.index','Settings','settings'];
                }
            @endphp
            @foreach($links as [$route,$label,$icon]) <a href="{{ route($route) }}" class="{{ request()->routeIs($route) ? 'nav-active' : 'hover:bg-slate-800 hover:text-white' }} flex items-center gap-3 rounded-lg px-3 py-2.5"><i data-lucide="{{ $icon }}" class="h-4 w-4"></i>{{ $label }}</a> @endforeach
        </nav>
        <div class="mt-auto border-t border-slate-800 pt-4 text-xs text-slate-500">Internal administration<br>Mock data environment</div>
    </aside>
    <div class="min-w-0 flex-1">
        <header class="flex h-16 items-center justify-between border-b border-slate-200 bg-white px-4 sm:px-7"><div class="flex items-center gap-3"><button @click="sidebar=!sidebar" class="rounded-md p-2 text-slate-600 lg:hidden" aria-label="Toggle menu"><i data-lucide="menu" class="h-5 w-5"></i></button><div><h1 class="text-lg font-semibold">{{ $title ?? 'Dashboard' }}</h1><p class="hidden text-xs text-slate-500 sm:block">shaafi Combo portal</p></div></div><div class="flex items-center gap-4"><button class="rounded-md p-2 text-slate-500" aria-label="Notifications"><i data-lucide="bell" class="h-5 w-5"></i></button><div class="flex items-center gap-2 border-l pl-4"><span class="grid h-8 w-8 place-items-center rounded-full bg-teal-100 text-xs font-bold text-teal-700">{{ strtoupper(substr(auth()->user()->displayName(), 0, 2)) }}</span><div class="hidden sm:block"><p class="text-sm font-medium">{{ auth()->user()->displayName() }}</p><p class="text-xs text-slate-500">{{ auth()->user()->roleLabel() }}</p><form method="POST" action="{{ route('logout') }}">@csrf <button type="submit" class="text-xs text-slate-500 hover:text-teal-700">Logout</button></form></div></div></div></header>
        <main class="p-4 sm:p-7">{{ $slot }}</main>
    </div>
</div><script>lucide.createIcons()</script>
</body></html>
