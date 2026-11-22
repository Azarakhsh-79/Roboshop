<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnerVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_id',
        'token',
        'verification_status',
        'full_name',
        'phone_number',
        'national_id',
        'id_photo_file_id',
        'notes',
        'submitted_at',
        'reviewed_at'
    ];



    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at'  => 'datetime',
    ];

    /**
     * ارتباط با ربات (ManagedBot)
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(ManagedBot::class, 'bot_id');
    }
}
