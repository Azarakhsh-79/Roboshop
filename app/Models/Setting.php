<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_id',
        'key',
        'value',
        'description',
        'type',
        'options',
    ];

    protected $casts = [
        'bot_id' => 'integer',
        'options' => 'array',
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(ManagedBot::class, 'bot_id');
    }
}
