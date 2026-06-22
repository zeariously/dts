<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class RegisteredUserController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'loginname' => ['required', 'string', 'max:50', 'unique:username,loginname'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

       User::create([
    'loginname' => $request->loginname,
    'name' => $request->name,
    'password' => Hash::make($request->password),
    'rights' => 2,
    'idoffice' => 0,
    'idmapagency' => $request->input('idmapagency') ?? 0,
    'lastlogin' => now(),
]);

return redirect()
    ->route('login')
    ->with('status', 'Account created successfully. You may now log in.');
    }
}