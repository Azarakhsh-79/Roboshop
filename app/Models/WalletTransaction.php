<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_id',
        'user_id',
        'invoice_id',
        'amount',
        'type',
        'description',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bot()
    {
        return $this->belongsTo(ManagedBot::class, 'bot_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
