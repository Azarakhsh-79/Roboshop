<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_id',
        'user_id',
        'plan_key',
        'plan_name',
        'duration_days',
        'amount',
        'receipt_file_id',
        'status',
        'approver_chat_id',
        'authority', // 👈 این فیلد اضافه شد
        'ref_id'     // 👈 این فیلد اضافه شد
    ];

    protected $casts = [
        'amount'        => 'integer',
        'duration_days' => 'integer',
        'bot_id'        => 'integer',
        'user_id'       => 'integer',
    ];

    /**
     * ارتباط با ربات (ManagedBot)
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(ManagedBot::class, 'bot_id');
    }

    /**
     * ارتباط با کاربر (User)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
