<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · shaafi Combo portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body{font-family:Inter,ui-sans-serif,system-ui,sans-serif}</style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800">
    <main class="mx-auto flex min-h-screen max-w-md items-center px-5 py-10">
        <section class="w-full rounded-2xl border border-slate-200 bg-white p-7 shadow-sm sm:p-9">
            <div class="mb-8 flex items-center gap-3"><img src="{{ asset('images/shaafi-portal-logo.svg') }}" alt="shaafi Combo portal" class="h-10 w-10 rounded-lg object-contain"><div><h1 class="text-lg font-semibold">shaafi Combo portal</h1><p class="text-sm text-slate-500">Sign in to continue</p></div></div>
            <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
                @csrf
                <div><label for="username" class="mb-1.5 block text-sm font-medium">Username</label><input id="username" name="username" value="{{ old('username') }}" required autofocus autocomplete="username" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 outline-none transition focus:border-teal-500 focus:ring-2 focus:ring-teal-100"><x-auth-error field="username" /></div>
                <div><label for="password" class="mb-1.5 block text-sm font-medium">Password</label><input id="password" type="password" name="password" required autocomplete="current-password" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 outline-none transition focus:border-teal-500 focus:ring-2 focus:ring-teal-100"><x-auth-error field="password" /></div>
                <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="remember" value="1" class="rounded border-slate-300 text-teal-600 focus:ring-teal-500"> Remember me</label>
                <button type="submit" class="w-full rounded-lg bg-teal-600 px-4 py-2.5 font-medium text-white transition hover:bg-teal-700">Login</button>
            </form>
        </section>
    </main>
</body>
</html>
