<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    protected $fillable = [
        'section_id',
        'title',
        'description',
        'type',
        'lesson_url',
        'duration',
        'sort_order',
        'is_preview'
    ];

    public function section()
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function lessonProgresses()
    {
        return $this->hasMany(LessonProgress::class);
    }
}
