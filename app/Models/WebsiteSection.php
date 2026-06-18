<?php

namespace App\Models;

use App\Models\Concerns\LogsAdminActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebsiteSection extends Model
{
    use LogsAdminActivity;

    protected $fillable = [
        'section_key',
        'eyebrow',
        'title',
        'subtitle',
        'body',
        'image_url',
        'cta_label',
        'cta_url',
        'secondary_cta_label',
        'secondary_cta_url',
        'is_active',
        'hero_images',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'hero_images' => 'array',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(WebsiteSectionItem::class, 'section_id')->orderBy('id');
    }
}
