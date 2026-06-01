<?php

namespace App\Models;

use App\Models\Concerns\LogsAdminActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteSectionItem extends Model
{
    use LogsAdminActivity;

    protected $fillable = [
        'section_id',
        'title',
        'description',
        'icon',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(WebsiteSection::class, 'section_id');
    }
}
