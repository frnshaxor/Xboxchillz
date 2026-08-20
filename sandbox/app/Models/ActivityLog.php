<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = ['user_id', 'action', 'detail', 'ip'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
