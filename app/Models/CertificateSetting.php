<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateSetting extends Model
{
    protected $fillable = [
        'organization_name',
        'certificate_title',
        'certificate_prefix',
        'signatory_name',
        'signatory_title',
        'signature_image',
        'background_image',
        'footer_note',
        'expires_after_months',
    ];

    protected $casts = [
        'expires_after_months' => 'integer',
    ];
}
