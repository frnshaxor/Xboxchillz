<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessToken;
use App\Models\Category;
use App\Models\Video;
use App\Services\TokenAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'categories' => Category::query()->withCount('videos')->orderBy('name')->get(),
            'videos' => Video::query()->with('category')->latest()->get(),
            'tokens' => AccessToken::query()->latest()->get(),
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        Category::query()->firstOrCreate(['name' => trim($data['name'])]);

        return back()->with('status', 'Kategori disimpan.');
    }

    public function storeToken(Request $request, TokenAccess $tokens): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'min:2', 'max:120'],
            'contact_type' => ['required', 'in:telegram,whatsapp,facebook'],
            'contact_value' => ['required', 'string', 'min:2', 'max:200'],
        ]);
        [, $plain] = $tokens->issue($data['label'], $data['contact_type'], $data['contact_value'], $request->user()->id);

        return back()->with('issued_token', $plain);
    }

    public function toggleToken(AccessToken $accessToken): RedirectResponse
    {
        $accessToken->update(['status' => $accessToken->isActive() ? 'suspended' : 'active']);

        return back()->with('status', 'Status token diperbarui.');
    }

    public function destroyToken(AccessToken $accessToken): RedirectResponse
    {
        $accessToken->delete();

        return back()->with('status', 'Token dihapus.');
    }
}
