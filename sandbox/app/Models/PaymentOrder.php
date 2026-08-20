<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentOrder extends Model
{
    protected $fillable = ['order_id', 'buyer_name', 'buyer_contact', 'amount', 'status', 'snap_token', 'access_secret_hash', 'client_ip'];

    protected $casts = ['notification_payload' => 'array', 'paid_at' => 'datetime'];

    public function accessToken(): BelongsTo
    {
        return $this->belongsTo(AccessToken::class);
    }
}
