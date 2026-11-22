<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    use HasFactory;

    // اضافه کردن فیلد جدید به fillable
    protected $fillable = [
        'bot_id',
        'product_id',
        'file_id',
        'telegram_file_id',
        'type',
        'sort_order'
    ];

    protected static function boot()
    {
        parent::boot();

        static::updating(function ($image) {
            if ($image->isDirty('file_id')) {
                $image->telegram_file_id = null;
            }
        });
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
