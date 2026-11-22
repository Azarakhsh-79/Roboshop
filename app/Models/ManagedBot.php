<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ManagedBot extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_id_string',
        'owner_id',
        'owner_phone',
        'bot_token',
        'webhook_secret',
        'bot_name',
        'bot_username',
        'status',
        'subscription_plan',
        'subscription_expires_at',
        'stats_last_updated_at',
        'total_users',
        'new_users_today',
        'active_users_today',
        'total_products',
        'new_products_today',
        'user_growth_percent',
        'total_orders',
        'completed_orders',
        'pending_invoices',
        'todays_revenue',
        'total_revenue',
        'avg_user_purchase',
        'top_product'
    ];

    protected $casts = [
        'subscription_expires_at' => 'datetime',
        'stats_last_updated_at'   => 'datetime',
        'user_growth_percent'     => 'decimal:2',
        'todays_revenue'          => 'integer',
        'total_revenue'           => 'integer',
        'avg_user_purchase'       => 'integer',
    ];

    // --- روابط ---

    public function ownerVerification(): HasOne
    {
        return $this->hasOne(OwnerVerification::class, 'bot_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'bot_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'bot_id');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'bot_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'bot_id');
    }

    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class, 'bot_id');
    }

    public function plan()
    {
        return $this->belongsTo(\App\Models\Plan::class, 'id');
    }
}
