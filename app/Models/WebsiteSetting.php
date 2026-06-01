<?php

namespace App\Models;

use App\Models\Concerns\LogsAdminActivity;
use Illuminate\Database\Eloquent\Model;

class WebsiteSetting extends Model
{
    use LogsAdminActivity;

    protected $fillable = [
        'site_name',
        'site_tagline',
        'logo_url',
        'footer_text',
        'contact_email',
        'contact_phone',
        'address',
    ];
}
