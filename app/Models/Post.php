<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['wp_id', 'title', 'slug', 'body', 'image', 'meta_description', 'is_published', 'published_at'])]
class Post extends Model
{
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function images(): HasMany
    {
        return $this->hasMany(PostImage::class)->orderBy('position');
    }
}
