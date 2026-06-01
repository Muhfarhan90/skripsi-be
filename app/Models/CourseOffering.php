<?php

namespace App\Models;

use App\Models\Concerns\LogsAdminActivity;
use Illuminate\Database\Eloquent\Model;

class CourseOffering extends Model
{
    use LogsAdminActivity;

    protected $fillable = [
        'course_id',
        'academic_period_id',
        'capacity',
        'price',
        'discount_price',
        'is_active',
    ];

    protected $casts = [
        'price' => 'float',
        'discount_price' => 'float',
        'is_active' => 'boolean',
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
