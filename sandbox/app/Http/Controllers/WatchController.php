<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Services\TokenAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WatchController extends Controller
{
    public function show(Request $request, Video $video, TokenAccess $access): View
    {
        abort_unless($video->isReady(), 404);

        return view('watch', [
            'video' => $video->load('category'),
            'hasAccess' => $access->validFor($request),
        ]);
    }
}
