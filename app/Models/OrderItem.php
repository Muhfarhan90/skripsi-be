<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'course_offering_id',
        'price',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function courseOffering()
    {
        return $this->belongsTo(CourseOffering::class);
    }
}
