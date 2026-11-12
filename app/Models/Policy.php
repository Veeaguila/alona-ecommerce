<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Policy extends Model
{
    protected $fillable = ['title', 'content', 'version', 'is_published', 'updated_by'];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }
}
