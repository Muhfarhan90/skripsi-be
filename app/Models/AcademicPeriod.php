<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPeriod extends Model
{
    protected $fillable = [
        'code',
        'name',
        'start_at',
        'end_at',
        'enrollment_open_at',
        'enrollment_close_at',
        'is_active',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'enrollment_open_at' => 'datetime',
        'enrollment_close_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function courseOfferings()
    {
        return $this->hasMany(CourseOffering::class);
    }
}
