<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['wp_id', 'title', 'slug', 'body', 'meta_title', 'meta_description', 'is_published'])]
class Page extends Model
{
    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }
}
