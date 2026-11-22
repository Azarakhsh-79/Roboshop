<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'bot_id',
        'category_id',
        'name',
        'sku',
        'description',
        'price',
        'purchase_price',
        'discount_price',
        'discount_expires_at',
        'stock',
        'sales_count',
        'views_count',
        'average_rating',
        'review_count',
        'is_active',
        'channel_message_id',
    ];


    protected $casts = [
        'is_active' => 'boolean',
        'discount_expires_at' => 'datetime',
        'price' => 'integer',
        'discount_price' => 'integer',
        'purchase_price' => 'integer',
        'cost_price' => 'integer',
        'stock' => 'integer',
        'review_count' => 'integer',
    ];
    // ==========================================================
    // تولید خودکار کد محصول (SKU) در صورت خالی بودن
    // ==========================================================
    protected static function booted()
    {
        static::creating(function ($product) {
            if (empty($product->sku)) {
                // پیدا کردن بزرگترین کد عددی در بین محصولات همین ربات
                $lastProduct = static::where('bot_id', $product->bot_id)
                    ->orderByRaw('CAST(sku AS UNSIGNED) DESC')
                    ->first();

                $lastSku = $lastProduct ? (int) $lastProduct->sku : 0;

                // اگر کدهای قبلی بیشتر از 1000 بودن یدونه اضافه کن، در غیر این صورت از 1000 شروع کن
                $product->sku = ($lastSku >= 1000) ? (string) ($lastSku + 1) : '1000';
            }
        });
    }
    // --- روابط ---

    public function bot(): BelongsTo
    {
        return $this->belongsTo(ManagedBot::class, 'bot_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }


    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'product_id')->orderBy('sort_order');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class, 'product_id');
    }

    /**
     * متد کمکی برای محاسبه قیمت نهایی (با در نظر گرفتن تخفیف)
     */
    public function getFinalPriceAttribute(): int
    {
        if ($this->discount_price && $this->discount_price > 0) {
            // چک کردن تاریخ انقضای تخفیف
            if ($this->discount_expires_at && $this->discount_expires_at->isPast()) {
                return (int) $this->price;
            }
            return (int) $this->discount_price;
        }
        return (int) $this->price;
    }

    // در مدل Product.php
    public function getFinalPrice(): int
    {
        if ($this->discount_price > 0 && (empty($this->discount_expires_at) || $this->discount_expires_at->isFuture())) {
            return $this->discount_price;
        }
        return $this->price;
    }
}
