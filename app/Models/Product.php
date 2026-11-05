<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['wp_id', 'title', 'slug', 'verdict', 'body', 'rating', 'image', 'gallery', 'meta_description', 'is_published', 'published_at'])]
class Product extends Model
{
    protected function casts(): array
    {
        return [
            'rating' => 'decimal:1',
            'gallery' => 'array',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(ProductOffer::class)->orderBy('position');
    }
}
