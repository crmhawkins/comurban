<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    protected $fillable = [
        'name',
        'language',
        'category',
        'status',
        'components',
        'meta_template_id',
    ];

    protected function casts(): array
    {
        return [
            'components' => 'array',
        ];
    }
}
