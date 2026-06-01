<?php

namespace App\Models;

use App\Models\Concerns\LogsAdminActivity;
use Illuminate\Database\Eloquent\Model;

class WebsitePage extends Model
{
    use LogsAdminActivity;

    protected $fillable = [
        'slug',
        'title',
        'content',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
