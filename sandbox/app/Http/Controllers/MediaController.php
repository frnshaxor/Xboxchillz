<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Services\TokenAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaController extends Controller
{
    public function show(Request $request, Video $video, string $asset, TokenAccess $access): BinaryFileResponse
    {
        abort_unless($video->isReady() && $access->validFor($request), 403);
        abort_unless(preg_match('/^(?:master|360p|720p)\\.m3u8$|^(?:360p|720p)_\\d{3}\\.ts$|^(?:source\\.mp4|poster\\.jpg)$/', $asset), 404);

        $path = 'videos/'.$video->slug.'/'.$asset;
        abort_unless(Storage::disk('local')->exists($path), 404);

        $types = ['m3u8' => 'application/vnd.apple.mpegurl', 'ts' => 'video/mp2t', 'mp4' => 'video/mp4', 'jpg' => 'image/jpeg'];
        $extension = pathinfo($asset, PATHINFO_EXTENSION);

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => $types[$extension] ?? 'application/octet-stream',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
