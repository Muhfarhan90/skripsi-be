<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'user_id',
        'course_id',
        'voucher_id',
        'invoice_number',
        'subtotal',
        'discount',
        'tax',
        'admin_fee',
        'grand_total',
        'status',
        'payment_method',
        'payment_reference',
        'payment_proof',
        'notes',
        'paid_at',
        'expired_at',
        'verified_by'
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function voucher()
    {
        return $this->belongsTo(Voucher::class, 'voucher_id');
    }


    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
