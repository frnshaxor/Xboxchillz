<?php

namespace App\Services;

use App\Models\AccessToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TokenAccess
{
    public const SESSION_KEY = 'access_token_id';

    public function issue(string $label, string $contactType, string $contactValue, ?int $createdBy = null): array
    {
        do {
            $plain = implode('-', str_split(Str::upper(Str::random(12)), 4));
            $hash = hash('sha256', $plain);
        } while (AccessToken::query()->where('token_hash', $hash)->exists());

        $token = AccessToken::query()->create([
            'token_hash' => $hash,
            'label' => $label,
            'contact_type' => $contactType,
            'contact_value' => $contactValue,
            'status' => 'active',
            'created_by' => $createdBy,
        ]);

        return [$token, $plain];
    }

    public function grant(Request $request, string $plain): bool
    {
        $hash = hash('sha256', Str::upper(trim($plain)));
        $token = AccessToken::query()->where('token_hash', $hash)->where('status', 'active')->first();
        if (! $token) return false;

        DB::transaction(function () use ($token): void {
            $token->increment('use_count');
            $token->forceFill(['last_used_at' => now()])->save();
        });
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, $token->id);

        return true;
    }

    public function validFor(Request $request): bool
    {
        if ($request->user()?->is_admin && $request->user()?->active) return true;
        $id = $request->session()->get(self::SESSION_KEY);
        if (! $id || ! AccessToken::query()->whereKey($id)->where('status', 'active')->exists()) {
            $request->session()->forget(self::SESSION_KEY);
            return false;
        }
        return true;
    }

    public function revoke(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
        $request->session()->regenerateToken();
    }
}
