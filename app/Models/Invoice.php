<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Http;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_id',
        'user_id',
        'tracking_code',
        'total_amount',
        'discount_amount',
        'status',
        'receipt_file_id',
        'user_info',
        'applied_discount_code',
        'wallet_amount_used'
    ];

    protected $casts = [
        'user_info'       => 'array',
        'total_amount'    => 'integer',
        'discount_amount' => 'integer',
    ];

    // 💡 اضافه کردن فیلدهای مجازی به مدل تا در کل برنامه در دسترس باشند
    protected $appends = ['final_amount', 'user_address_id'];

    /**
     * 🧮 محاسبه خودکار مبلغ نهایی (مبلغ کل منهای تخفیف)
     */
    public function getFinalAmountAttribute()
    {
        return $this->total_amount - $this->discount_amount;
    }

    /**
     * 📍 خواندن خودکار آدرس از داخل ستون JSON
     */
    public function getUserAddressIdAttribute()
    {
        $info = $this->user_info ?? [];
        return $info['address_id'] ?? null;
    }

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
     * آیتم‌های فاکتور
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id');
    }
    /**
     * تراکنش‌های مربوط به این فاکتور
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'invoice_id');
    }

    protected static function booted()
    {
        static::updated(function ($invoice) {
            // بررسی می‌کنیم که آیا فیلد status در این آپدیت تغییر کرده است؟
            if ($invoice->isDirty('status')) {
                $newStatus = $invoice->status;

                // لیست وضعیت‌هایی که ادمین دخالت دارد و باید به کاربر اطلاع داده شود
                if (in_array($newStatus, ['processing', 'shipped', 'delivered', 'canceled'])) {

                    $statusText = match($newStatus) {
                        'processing' => 'در حال پردازش (تایید توسط مدیریت) ⏳',
                        'shipped' => 'تحویل پست / ارسال شد 📦',
                        'delivered' => 'تحویل مشتری داده شد ✅',
                        'canceled' => 'لغو شده ❌',
                        default => $newStatus
                    };

                    $message = "🔔 <b>بروزرسانی وضعیت سفارش</b>\n\nکاربر گرامی، سفارش شما با کد پیگیری <code>{$invoice->tracking_code}</code> به وضعیت <b>{$statusText}</b> تغییر یافت.";

                    // ارسال درخواست به ربات تلگرام
                    Http::post("https://api.telegram.org/bot{$invoice->bot->bot_token}/sendMessage", [
                        'chat_id' => $invoice->user->chat_id,
                        'text' => $message,
                        'parse_mode' => 'HTML'
                    ]);
                }
            }
        });
    }
}
