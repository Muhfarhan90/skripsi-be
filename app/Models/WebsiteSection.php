<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebsiteSection extends Model
{
    protected $fillable = [
        'page_key',
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
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(WebsiteSectionItem::class, 'section_id')->orderBy('sort_order')->orderBy('id');
    }
}
