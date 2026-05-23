<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteSetting extends Model
{
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
