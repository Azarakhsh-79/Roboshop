<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Favorite extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_id',
        'user_id',
        'product_id',
    ];

    protected $casts = [
        'bot_id'     => 'integer',
        'user_id'    => 'integer',
        'product_id' => 'integer',
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

    /**
     * ارتباط با محصول (Product)
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
