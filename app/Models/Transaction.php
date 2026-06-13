<?php

namespace App\Models;

use App\Models\Concerns\LogsAdminActivity;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use LogsAdminActivity;

    protected $fillable = [
        'order_id',
        'invoice_code',
        'external_id',
        'payment_method',
        'payment_channel',
        'payment_url',
        'payment_reference',
        'amount',
        'status',
        'paid_at',
        'expired_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
