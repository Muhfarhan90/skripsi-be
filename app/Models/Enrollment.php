<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    protected $fillable = [
        'user_id',
        'course_offering_id',
        'order_id',
        'last_lesson_id',
        'progress',
        'status',
        'completed_at',
        'started_at',
        'ended_at',
        'expired_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function courseOffering()
    {
        return $this->belongsTo(CourseOffering::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function lastLesson()
    {
        return $this->belongsTo(Lesson::class, 'last_lesson_id');
    }

    public function lessonProgresses()
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function quizAttempts()
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    public function certificate()
    {
        return $this->hasOne(Certificate::class);
    }

    public function assignmentSubmissions()
    {
        return $this->hasMany(AssignmentSubmission::class);
    }
}
