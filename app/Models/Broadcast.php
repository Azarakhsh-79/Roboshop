<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Broadcast extends Model
{
    protected $fillable = [
        'bot_id', 'message', 'photo_file_id', 'scheduled_at', 'status', 'total_users', 'sent_count'
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    public function bot()
    {
        return $this->belongsTo(ManagedBot::class, 'bot_id');
    }
}
