<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    protected $fillable = [
        'user_id',
        'course_id',
        'transaction_id',
        'last_lesson_id',
        'progress',
        'status',
        'completed_at',
        'expired_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function lastLesson()
    {
        return $this->belongsTo(Lesson::class, 'last_lesson_id');
    }
}
