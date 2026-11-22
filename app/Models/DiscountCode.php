<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_id',
        'code',
        'type',
        'value',
        'usage_limit',
        'usage_count',
        'min_purchase_amount',
        'expires_at',
        'is_active'
    ];

    protected $casts = [
        'expires_at'          => 'datetime',
        'is_active'           => 'boolean',
        'value'               => 'decimal:2', // برای حفظ دقت اعشار
        'min_purchase_amount' => 'integer',   // چون در دیتابیس unsignedBigInteger است
    ];

    /**
     * ارتباط با ربات (ManagedBot)
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(ManagedBot::class, 'bot_id');
    }
}
