<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    protected $fillable = [
        'course_id',
        'title',
        'sort_order',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function lessons()
    {
        return $this->hasMany(Lesson::class, 'section_id');
    }

    public function quizzes()
    {
        return $this->hasMany(Quiz::class, 'section_id');
    }
}
