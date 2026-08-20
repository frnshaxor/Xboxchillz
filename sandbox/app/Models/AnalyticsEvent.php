<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class AnalyticsEvent extends Model
{
    protected $fillable = ['event', 'path', 'visitor_hash', 'video_id', 'progress_seconds', 'device', 'browser', 'referrer'];

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }
}
