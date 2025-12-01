<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Order extends Model
{
    use HasFactory;
    protected $fillable = [
        'hold_id',
        'status',
    ];

    public function hold()
    {
        return $this->belongsTo(Hold::class);
    }

    public function webhook()
    {
        return $this->hasOne(PaymentWebhook::class, 'order_id');
    }
}
