<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['wp_id', 'title', 'description', 'file', 'position'])]
class Song extends Model {}
