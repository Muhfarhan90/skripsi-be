<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    protected $fillable = [
        'user_id',
        'course_id',
        'enrollment_id',
        'certificate_number',
        'certificate_url',
        'status',
        'template_version',
        'verification_code',
        'snapshot_data',
        'issued_at',
        'expired_at',
        'revoked_at',
        'revoked_reason',
    ];

    protected $casts = [
        'snapshot_data' => 'array',
        'issued_at' => 'datetime',
        'expired_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }
}
