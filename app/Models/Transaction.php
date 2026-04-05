<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'order_id',
        'invoice_code',
        'external_id',
        'payment_method',
        'payment_channel',
        'payment_url',
        'payment_reference',
        'payment_proof',
        'amount',
        'status',
        'paid_at',
        'expired_at',
        'verified_by'
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
