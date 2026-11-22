<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'features',
        'limits',     
        'prices',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'features'   => 'array',
        'limits'     => 'array',
        'prices'     => 'array',
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];
}
