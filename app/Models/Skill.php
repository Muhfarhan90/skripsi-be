<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'is_active',
    ];

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'course_skills')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderBy('course_skills.sort_order')
            ->orderBy('skills.name');
    }
}
