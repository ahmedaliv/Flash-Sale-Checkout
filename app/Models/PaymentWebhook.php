<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Order;

class PaymentWebhook extends Model
{
    protected $fillable = [
        'order_id',
        'idempotency_key',
        'status',
        'processed',
    ];

    public function order()
    {
        return $this->belongsto(Order::class);
    }
}
