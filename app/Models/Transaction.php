<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_id',
        'user_id',
        'invoice_id',
        'amount',
        'gateway',
        'reference_id',
        'tracking_code',
        'status',
        'description',
        'gateway_response',
    ];

    protected $casts = [
        'amount' => 'integer',
        'gateway_response' => 'array', // تبدیل اتوماتیک JSON به آرایه
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(ManagedBot::class, 'bot_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }
}
