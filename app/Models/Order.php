<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'voucher_id',
        'order_code',
        'subtotal',
        'discount',
        'tax',
        'admin_fee',
        'note',
        'grand_total',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
