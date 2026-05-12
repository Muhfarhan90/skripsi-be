<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assignment extends Model
{
    protected $fillable = [
        'course_id',
        'section_id',
        'created_by',
        'title',
        'description',
        'instructions',
        'due_at',
        'is_required_for_certificate',
        'allow_resubmission',
        'max_attempts',
        'status',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'is_required_for_certificate' => 'boolean',
        'allow_resubmission' => 'boolean',
        'max_attempts' => 'integer',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submissions()
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function latestSubmission()
    {
        return $this->hasOne(AssignmentSubmission::class)->latestOfMany('attempt_no');
    }
}
