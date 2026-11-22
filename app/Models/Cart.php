<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_id',
        'user_id',
        'product_id',
        'variant_id',
        'quantity'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'bot_id'   => 'integer',
        'user_id'  => 'integer',
        'product_id' => 'integer',
        'variant_id' => 'integer',
    ];

    /**
     * ارتباط با ربات
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(ManagedBot::class, 'bot_id');
    }

    /**
     * ارتباط با کاربر
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * ارتباط با محصول
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * ارتباط با تنوع محصول (Variant)
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
