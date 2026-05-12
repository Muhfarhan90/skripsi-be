<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseOffering extends Model
{
    protected $fillable = [
        'course_id',
        'academic_period_id',
        'title',
        'start_at',
        'end_at',
        'enrollment_open_at',
        'enrollment_close_at',
        'capacity',
        'price',
        'discount_price',
        'status',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'enrollment_open_at' => 'datetime',
        'enrollment_close_at' => 'datetime',
        'price' => 'float',
        'discount_price' => 'float',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function academicPeriod()
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

}
