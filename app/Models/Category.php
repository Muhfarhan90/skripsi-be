<?php

namespace App\Models;

use App\Models\Concerns\LogsAdminActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use LogsAdminActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    public function courses()
    {
        return $this->hasMany(Course::class, 'category_id');
    }
}
