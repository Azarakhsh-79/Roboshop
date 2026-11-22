<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Filament\Models\Contracts\HasName;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable implements HasName, FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'bot_id',
        'chat_id',
        'username',
        'phone',
        'first_name',
        'last_name',
        'language',
        'email',
        'password',
        'is_admin',
        'last_used_address_id',
        'spam_info',
        'admin_notes',
        'status',
        'entry_token',
        'otp_code',
        'otp_expires_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_admin' => 'integer',
        'spam_info' => 'array',
        'email_verified_at' => 'datetime',
    ];

    // --- روابط ---

    public function bot(): BelongsTo
    {
        return $this->belongsTo(ManagedBot::class, 'bot_id');
    }

    public function shops(): HasMany
    {
        return $this->hasMany(ManagedBot::class, 'owner_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(UserAddress::class, 'user_id');
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(Cart::class, 'user_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'user_id');
    }

    // --- توابع Filament ---

    /**
     * تامین نام نمایشی کاربر در گوشه پنل فیلامنت
     */
    public function getFilamentName(): string
    {
        // ۱. اولویت اول: نام و نام خانوادگی
        $fullName = trim($this->first_name . ' ' . $this->last_name);
        if (!empty($fullName)) {
            return $fullName;
        }

        // ۲. اولویت دوم: یوزرنیم (استفاده از empty برای جلوگیری از باگ رشته‌های خالی)
        if (!empty($this->username)) {
            return $this->username;
        }

        // ۳. اولویت سوم: شماره موبایل
        if (!empty($this->phone)) {
            return $this->phone;
        }

        // ۴. مقدار پیش‌فرض قطعی
        return 'کاربر عزیز';
    }

    /**
     * مدیریت سطح دسترسی به پنل‌های مختلف
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // دسترسی به پنل مدیریت کل (Super Admin)
        if ($panel->getId() === 'superadmin') {
            return $this->phone == '09334590631'; // شماره موبایل مالک اصلی سیستم
        }

        // دسترسی به پنل مالکین ربات (Admin)
        if ($panel->getId() === 'admin') {
            return $this->status === 'active';
        }

        return false;
    }

    public function walletTransactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    // متد جامع برای تغییر موجودی کیف پول
    public function modifyWallet($amount, $type, $description = null, $invoiceId = null)
    {
        $this->increment('wallet_balance', $amount);

        return $this->walletTransactions()->create([
            'bot_id' => $this->bot_id,
            'invoice_id' => $invoiceId,
            'amount' => $amount,
            'type' => $type,
            'description' => $description,
        ]);
    }
}
