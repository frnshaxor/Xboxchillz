<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    protected $fillable = ['title', 'slug', 'category_id', 'poster_path', 'source_path', 'duration_seconds', 'size_bytes', 'status'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }
}
