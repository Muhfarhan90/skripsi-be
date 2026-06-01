<?php

namespace App\Models;

use App\Models\Concerns\LogsAdminActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Faq extends Model
{
    use LogsAdminActivity;

    protected $fillable = [
        'question',
        'answer',
        'faq_category_id',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(FaqCategory::class, 'faq_category_id');
    }
}
