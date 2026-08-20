<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessToken;
use App\Models\AnalyticsEvent;
use App\Models\Setting;
use App\Models\Video;
use App\Services\TokenAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardApiController extends Controller
{
    private const PUBLIC_SETTINGS = [
        'site_name', 'site_description', 'accent', 'theme_key', 'watermark_text',
        'watermark_position', 'watermark_opacity', 'upload_max_mb', 'maintenance_mode',
    ];

    public function overview(): JsonResponse
    {
        $settings = Setting::query()->whereIn('key', self::PUBLIC_SETTINGS)->pluck('value', 'key');

        return response()->json([
            'settings' => $settings,
            'metrics' => [
                'videos' => Video::query()->count(),
                'ready' => Video::query()->where('status', 'ready')->count(),
                'tokens' => AccessToken::query()->where('status', 'active')->count(),
                'views' => Video::query()->sum('views'),
            ],
        ]);
    }

    public function analytics(Request $request): JsonResponse
    {
        $days = max(1, min(90, $request->integer('days', 30)));
        $since = now()->subDays($days);
        $events = AnalyticsEvent::query()->where('created_at', '>=', $since);

        return response()->json([
            'days' => $days,
            'metrics' => [
                'total' => (clone $events)->count(),
                'visitors' => (clone $events)->distinct('visitor_hash')->count('visitor_hash'),
                'page_views' => (clone $events)->where('event', 'page_view')->count(),
                'video_views' => (clone $events)->where('event', 'video_start')->count(),
            ],
            'popular' => AnalyticsEvent::query()->select('path', DB::raw('COUNT(*) as views'))
                ->where('event', 'page_view')->where('created_at', '>=', $since)->groupBy('path')->orderByDesc('views')->limit(10)->get(),
            'devices' => AnalyticsEvent::query()->select('device', DB::raw('COUNT(*) as count'))
                ->where('event', 'page_view')->where('created_at', '>=', $since)->groupBy('device')->orderByDesc('count')->get(),
        ]);
    }

    public function tokens(): JsonResponse
    {
        return response()->json(['tokens' => AccessToken::query()->latest()->get()]);
    }

    public function createToken(Request $request, TokenAccess $tokens): JsonResponse
    {
        $data = $this->validatedToken($request);
        [$token, $plain] = $tokens->issue($data['label'], $data['contact_type'], $data['contact_value'], $request->user()->id);

        return response()->json(['token' => $token, 'plain_token' => $plain], 201);
    }

    public function updateToken(Request $request, AccessToken $accessToken): JsonResponse
    {
        $accessToken->update($this->validatedToken($request));

        return response()->json(['token' => $accessToken->fresh()]);
    }

    public function toggleToken(AccessToken $accessToken): JsonResponse
    {
        $accessToken->update(['status' => $accessToken->isActive() ? 'suspended' : 'active']);

        return response()->json(['token' => $accessToken->fresh()]);
    }

    public function deleteToken(AccessToken $accessToken): JsonResponse
    {
        $accessToken->delete();

        return response()->json(status: 204);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'site_name' => ['sometimes', 'string', 'max:120'],
            'site_description' => ['sometimes', 'string', 'max:500'],
            'accent' => ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme_key' => ['sometimes', 'in:ivory,obsidian,emerald'],
            'watermark_text' => ['sometimes', 'string', 'max:40'],
            'watermark_position' => ['sometimes', 'in:tl,tr,bl,br,center'],
            'watermark_opacity' => ['sometimes', 'integer', 'between:10,100'],
            'upload_max_mb' => ['sometimes', 'integer', 'between:10,20480'],
            'maintenance_mode' => ['sometimes', 'boolean'],
        ]);
        foreach ($data as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value]);
        }

        return response()->json(['settings' => Setting::query()->whereIn('key', array_keys($data))->pluck('value', 'key')]);
    }

    private function validatedToken(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'min:2', 'max:120'],
            'contact_type' => ['required', 'in:telegram,whatsapp,facebook'],
            'contact_value' => ['required', 'string', 'min:2', 'max:200'],
        ]);
    }
}
