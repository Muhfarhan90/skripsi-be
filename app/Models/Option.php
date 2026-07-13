<?php

namespace App\Models;

use App\Models\Concerns\LogsAdminActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Option extends Model
{
    use LogsAdminActivity, SoftDeletes;

    protected $fillable = [
        'question_id',
        'option_text',
        'image_url',
        'is_correct'
    ];

    public function question()
    {
        return $this->belongsTo(Question::class, 'question_id');
    }

    public function answers()
    {
        return $this->hasMany(QuizAnswer::class, 'selected_option_id');
    }
}
