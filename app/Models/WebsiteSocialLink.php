<?php

namespace App\Models;

use App\Models\Concerns\LogsAdminActivity;
use Illuminate\Database\Eloquent\Model;

class WebsiteSocialLink extends Model
{
    use LogsAdminActivity;

    protected $fillable = [
        'label',
        'url',
        'icon',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
