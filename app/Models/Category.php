<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_id',
        'name',
        'description',
        'parent_id',
        'is_active',
        'sort_order'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * ارتباط با ربات (ManagedBot)
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(ManagedBot::class, 'bot_id');
    }



    /**
     * رابطه با دسته والد
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * رابطه با زیردسته‌ها (Children)
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * محصولات این دسته
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    protected static function booted()
    {
        // 1. هنگام ساخت دسته‌بندی جدید
        static::creating(function ($category) {
            $query = static::where('bot_id', $category->bot_id)
                ->where('parent_id', $category->parent_id);

            $max = $query->max('sort_order') ?? -1;

            // اگر عدد وارد شده خیلی بزرگ بود یا خالی بود، بره آخر لیست
            if ($category->sort_order === null || $category->sort_order > $max + 1) {
                $category->sort_order = $max + 1;
            } else {
                // در غیر این صورت، بقیه رو به پایین شیفت بده تا جا باز بشه
                $query->where('sort_order', '>=', $category->sort_order)->increment('sort_order');
            }
        });

        // 2. هنگام ویرایش دسته‌بندی (تغییر ترتیب یا تغییر والد)
        static::updating(function ($category) {
            if ($category->isDirty('sort_order') || $category->isDirty('parent_id')) {
                $originalParent = $category->getOriginal('parent_id');
                $originalOrder = $category->getOriginal('sort_order');
                $newParent = $category->parent_id;
                $newOrder = $category->sort_order;

                // اگر والد تغییر کرده باشه (انتقال به یک شاخه دیگه)
                if ($originalParent !== $newParent) {
                    // از لیست قبلی حذفش کن و بقیه رو شیفت بده بالا
                    static::where('bot_id', $category->bot_id)
                        ->where('parent_id', $originalParent)
                        ->where('sort_order', '>', $originalOrder)
                        ->decrement('sort_order');

                    // تو لیست جدید جاش بده
                    $max = static::where('bot_id', $category->bot_id)
                        ->where('parent_id', $newParent)
                        ->max('sort_order') ?? -1;

                    if ($newOrder === null || $newOrder > $max + 1) {
                        $category->sort_order = $max + 1;
                    } else {
                        static::where('bot_id', $category->bot_id)
                            ->where('parent_id', $newParent)
                            ->where('sort_order', '>=', $newOrder)
                            ->increment('sort_order');
                    }
                }
                // اگر فقط تو همون شاخه خودش بالا پایین شده باشه
                else {
                    $max = static::where('bot_id', $category->bot_id)
                        ->where('parent_id', $newParent)
                        ->max('sort_order') ?? -1;

                    if ($newOrder === null || $newOrder > $max) {
                        $newOrder = $max;
                        $category->sort_order = $newOrder;
                    }

                    if ($originalOrder < $newOrder) {
                        // رفت پایین: اونایی که بینش بودن میان بالا
                        static::where('bot_id', $category->bot_id)
                            ->where('parent_id', $newParent)
                            ->whereBetween('sort_order', [$originalOrder + 1, $newOrder])
                            ->decrement('sort_order');
                    } elseif ($originalOrder > $newOrder) {
                        // اومد بالا: اونایی که بینش بودن میرن پایین
                        static::where('bot_id', $category->bot_id)
                            ->where('parent_id', $newParent)
                            ->whereBetween('sort_order', [$newOrder, $originalOrder - 1])
                            ->increment('sort_order');
                    }
                }
            }
        });

        // 3. هنگام حذف دسته‌بندی (پر کردن جای خالی)
        static::deleted(function ($category) {
            static::where('bot_id', $category->bot_id)
                ->where('parent_id', $category->parent_id)
                ->where('sort_order', '>', $category->sort_order)
                ->decrement('sort_order');
        });
    }
}
