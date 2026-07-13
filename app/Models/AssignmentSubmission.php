<?php

namespace App\Models;

use App\Models\Concerns\LogsAdminActivity;
use Illuminate\Database\Eloquent\Model;

class AssignmentSubmission extends Model
{
    use LogsAdminActivity;

    protected $fillable = [
        'assignment_id',
        'enrollment_id',
        'user_id',
        'attempt_no',
        'submission_text',
        'attachment_url',
        'status',
        'review_notes',
        'reviewed_by',
        'submitted_at',
        'reviewed_at',
        'assignment_snapshot',
    ];

    protected $casts = [
        'attempt_no' => 'integer',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'assignment_snapshot' => 'array',
    ];

    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
