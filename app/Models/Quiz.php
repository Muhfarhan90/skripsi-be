<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    protected $fillable = [
        'course_id',
        'section_id',
        'title',
        'description',
        'duration',
        'passing_score',
        'weight',
        'is_active',
        'max_attempts'
    ];

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function section()
    {
        return $this->belongsTo(Section::class, 'section_id');
    }
}
