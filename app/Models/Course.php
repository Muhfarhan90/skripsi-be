<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'description',
        'category_id',
        'instructor_id',
        'price',
        'discount_price',
        'thumbnail',
        'status',
        'total_duration',
        'requirements',
        'outcomes',
        'total_students',
        'rating'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function sections()
    {
        return $this->hasMany(Section::class, 'course_id');
    }

    public function quizzes()
    {
        return $this->hasMany(Quiz::class, 'quiz_id');
    }
}
