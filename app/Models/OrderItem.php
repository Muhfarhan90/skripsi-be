<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'course_offering_id',
        'course_title',
        'course_slug',
        'period_code',
        'period_name',
        'course_offering_snapshot',
        'price',
    ];

    protected $casts = [
        'course_offering_snapshot' => 'array',
        'price' => 'float',
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
