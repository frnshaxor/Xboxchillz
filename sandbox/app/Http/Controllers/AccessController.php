<?php

namespace App\Http\Controllers;

use App\Services\TokenAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AccessController extends Controller
{
    public function store(Request $request, TokenAccess $access): RedirectResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:64']]);
        if (! $access->grant($request, $data['token'])) {
            return back()->withErrors(['token' => 'Token tidak valid atau telah dinonaktifkan.']);
        }

        return redirect()->intended(route('catalog.index'));
    }

    public function destroy(Request $request, TokenAccess $access): RedirectResponse
    {
        $access->revoke($request);

        return redirect()->route('catalog.index');
    }
}
