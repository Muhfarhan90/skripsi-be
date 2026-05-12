<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'category_id',
        'instructor_id',
        'thumbnail',
        'total_duration',
        'requirements',
        'outcomes',
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
        return $this->hasMany(Quiz::class, 'course_id');
    }

    public function enrollments()
    {
        return $this->hasManyThrough(
            Enrollment::class,
            CourseOffering::class,
            'course_id',
            'course_offering_id',
            'id',
            'id'
        );
    }

    public function courseOfferings()
    {
        return $this->hasMany(CourseOffering::class);
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }

    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'course_skills')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderBy('course_skills.sort_order')
            ->orderBy('skills.name');
    }
}
