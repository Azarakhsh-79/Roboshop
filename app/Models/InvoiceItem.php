<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_id',
        'invoice_id',
        'product_id',
        'variant_id',      // 👈 اصلاح شد
        'name_at_purchase',// 👈 اضافه شد
        'quantity',
        'price',           // 👈 اصلاح شد
    ];
    
    protected $casts = [
        'quantity' => 'integer',
        'price' => 'integer',
        'bot_id' => 'integer',
        'invoice_id' => 'integer',
        'product_id' => 'integer',
        'variant_id' => 'integer',
    ];

    /**
     * ارتباط با فاکتور اصلی
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    /**
     * ارتباط با محصول
     * از withTrashed استفاده کردیم تا اگر محصولی در آینده حذف شد، تاریخچه فاکتورها خراب نشود
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id')->withTrashed();
    }

    /**
     * ارتباط با تنوع محصول (Variant)
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id')->withTrashed();
    }

    /**
     * ارتباط با ربات (برای امنیت داده‌ها)
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(ManagedBot::class, 'bot_id');
    }
}
