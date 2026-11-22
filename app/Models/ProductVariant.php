<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_id',
        'product_id',
        'variant_name',
        'price',
        'discount_price',
        'stock',
        'is_active'
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        // کست به integer برای هماهنگی با unsignedBigInteger دیتابیس
        'price'          => 'integer',
        'discount_price' => 'integer',
        'stock'          => 'integer',
        'bot_id'         => 'integer',
        'product_id'     => 'integer',
    ];

    /**
     * ارتباط با محصول
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * ارتباط با ربات (برای امنیت و ایزولاسیون داده‌ها)
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(ManagedBot::class, 'bot_id');
    }
}
