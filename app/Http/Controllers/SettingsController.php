<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function index()
    {
        return view('settings.index', ['users' => User::orderBy('firstName')->orderBy('last_name')->get()]);
    }

    public function storeUser(Request $request)
    {
        $data = $request->validate([
            'firstName' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:20'],
            'username' => ['required', 'string', 'max:255', 'unique:mysql_portal.users,username'],
            'password' => ['required', 'string', 'confirmed'],
            'status' => ['required', Rule::in(['ACTIVE', 'INIT'])],
        ]);

        User::create([
            'firstName' => $data['firstName'], 'last_name' => $data['last_name'],
            'number' => $data['number'] ?? null, 'username' => $data['username'],
            'password' => Hash::make($data['password']), 'status' => $data['status'],
            // This form is intentionally incapable of granting superadmin access.
            'role' => 'AGENT',
        ]);

        return redirect()->route('settings.index')->with('success', 'Agent user created.');
    }

    public function updateUserStatus(Request $request, User $user)
    {
        abort_if($user->isSuperAdmin(), 403, 'Superadmin accounts cannot be changed here.');
        $data = $request->validate(['status' => ['required', Rule::in(['ACTIVE', 'INIT', 'DELETED'])]]);
        $user->update(['status' => $data['status']]);

        return redirect()->route('settings.index')->with('success', 'Agent status updated.');
    }
}
