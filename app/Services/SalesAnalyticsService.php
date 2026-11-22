<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SalesAnalyticsService
{
    protected $botId;
    protected $startDate;
    protected $endDate;
    protected $successfulStatuses = ['paid', 'processing', 'shipped', 'delivered'];

    public function __construct($botId, $startDate = null, $endDate = null)
    {
        $this->botId = $botId;
        $this->startDate = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::today()->subDays(30);
        $this->endDate = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now();
    }

    /**
     * 🧮 متد کمکی برای محاسبه درصد رشد یا افت
     */
    private function calculateTrend($current, $previous)
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * 💰 استخراج KPI های اصلی همراه با مقایسه دوره قبل
     */
    public function getOverviewKPIs(): array
    {
        // محاسبه تاریخ دوره قبل (مثلاً اگر فیلتر روی 7 روز گذشته است، دوره قبل میشود 7 روز قبل‌تر از آن)
        $diffInDays = $this->startDate->diffInDays($this->endDate) ?: 1;
        $prevEndDate = $this->startDate->copy()->subSecond();
        $prevStartDate = $this->startDate->copy()->subDays($diffInDays);

        // -- کوئری دوره فعلی --
        $currentStats = Invoice::where('bot_id', $this->botId)
            ->whereIn('status', $this->successfulStatuses)
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->selectRaw('COUNT(id) as total_orders, SUM(total_amount) as gross_sales, SUM(discount_amount) as total_discount')
            ->first();

        // -- کوئری دوره قبل --
        $prevStats = Invoice::where('bot_id', $this->botId)
            ->whereIn('status', $this->successfulStatuses)
            ->whereBetween('created_at', [$prevStartDate, $prevEndDate])
            ->selectRaw('COUNT(id) as total_orders, SUM(total_amount) as gross_sales, SUM(discount_amount) as total_discount')
            ->first();

        // پردازش اعداد فعلی
        $currGross = $currentStats->gross_sales ?? 0;
        $currDiscount = $currentStats->total_discount ?? 0;
        $currNet = $currGross - $currDiscount;
        $currOrders = $currentStats->total_orders ?? 0;
        $currAov = $currOrders > 0 ? ($currNet / $currOrders) : 0;

        // پردازش اعداد قبلی
        $prevGross = $prevStats->gross_sales ?? 0;
        $prevDiscount = $prevStats->total_discount ?? 0;
        $prevNet = $prevGross - $prevDiscount;
        $prevOrders = $prevStats->total_orders ?? 0;
        $prevAov = $prevOrders > 0 ? ($prevNet / $prevOrders) : 0;

        return [
            'net_sales' => [
                'value' => $currNet,
                'trend' => $this->calculateTrend($currNet, $prevNet),
                'is_positive' => $currNet >= $prevNet
            ],
            'total_orders' => [
                'value' => $currOrders,
                'trend' => $this->calculateTrend($currOrders, $prevOrders),
                'is_positive' => $currOrders >= $prevOrders
            ],
            'aov' => [
                'value' => $currAov,
                'trend' => $this->calculateTrend($currAov, $prevAov),
                'is_positive' => $currAov >= $prevAov
            ],
            'total_discount' => [
                'value' => $currDiscount,
                'trend' => $this->calculateTrend($currDiscount, $prevDiscount),
                // برای تخفیف، اگر بیشتر داده باشیم منفی (قرمز) در نظر می‌گیریم چون از سود کم شده
                'is_positive' => $currDiscount <= $prevDiscount
            ],
        ];
    }

    /**
     * 🏆 استخراج محصولات پرفروش
     */
    public function getTopProducts($limit = 5)
    {
        return InvoiceItem::whereHas('invoice', function ($q) {
            $q->whereIn('status', $this->successfulStatuses)
                ->whereBetween('created_at', [$this->startDate, $this->endDate]);
        })
            ->where('bot_id', $this->botId)
            ->select('product_id', 'name_at_purchase', DB::raw('SUM(quantity) as total_sold'), DB::raw('SUM(quantity * price) as total_revenue'))
            ->groupBy('product_id', 'name_at_purchase')
            ->orderByDesc('total_revenue')
            ->take($limit)
            ->get();
    }

    /**
     * 👑 استخراج مشتریان طلایی (VIP)
     */
    public function getTopCustomers($limit = 5)
    {
        return User::where('bot_id', $this->botId)
            ->whereHas('invoices', function ($q) {
                $q->whereIn('status', $this->successfulStatuses)
                    ->whereBetween('created_at', [$this->startDate, $this->endDate]);
            })
            ->withCount([
                'invoices as successful_orders' => function ($q) {
                    $q->whereIn('status', $this->successfulStatuses)
                        ->whereBetween('created_at', [$this->startDate, $this->endDate]);
                }
            ])
            ->addSelect([
                'total_spent' => Invoice::selectRaw('SUM(total_amount - discount_amount)')
                    ->whereColumn('user_id', 'users.id')
                    ->whereIn('status', $this->successfulStatuses)
                    ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ])
            ->orderByDesc('total_spent')
            ->take($limit)
            ->get();
    }

    /**
     * 🧲 تحلیل قیف فروش و سبدهای رها شده
     */
    public function getFunnelAnalytics(): array
    {
        // کل فاکتورهای ایجاد شده در این بازه (موفق + ناموفق + رها شده)
        $totalCreated = Invoice::where('bot_id', $this->botId)
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->count();

        // فاکتورهای پرداخت شده و موفق
        $successful = Invoice::where('bot_id', $this->botId)
            ->whereIn('status', $this->successfulStatuses)
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->count();

        // سبدهای رها شده (فقط pending)
        $abandoned = Invoice::where('bot_id', $this->botId)
            ->where('status', 'pending')
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->count();

        // لغو شده یا ناموفق
        $failed = Invoice::where('bot_id', $this->botId)
            ->whereIn('status', ['canceled', 'failed'])
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->count();

        // محاسبه درصدها
        $conversionRate = $totalCreated > 0 ? round(($successful / $totalCreated) * 100, 1) : 0;
        $abandonmentRate = $totalCreated > 0 ? round(($abandoned / $totalCreated) * 100, 1) : 0;

        return [
            'total_created' => $totalCreated,
            'successful' => $successful,
            'abandoned' => $abandoned,
            'failed' => $failed,
            'conversion_rate' => $conversionRate,
            'abandonment_rate' => $abandonmentRate,
        ];
    }

    /**
     * 👥 استخراج آمار کلیدی و وفاداری مشتریان
     */
    public function getCustomerMetrics(): array
    {
        $totalBuyers = User::where('bot_id', $this->botId)
            ->whereHas('invoices', function ($q) {
                $q->whereIn('status', $this->successfulStatuses)
                    ->whereBetween('created_at', [$this->startDate, $this->endDate]);
            })->count();

        // مشتریان با بیش از یک سفارش در این بازه (مشتریان بازگشتی)
        $repeatBuyers = User::where('bot_id', $this->botId)
            ->whereHas('invoices', function ($q) {
                $q->whereIn('status', $this->successfulStatuses)
                    ->whereBetween('created_at', [$this->startDate, $this->endDate]);
            })
            ->withCount([
                'invoices as successful_count' => function ($q) {
                    $q->whereIn('status', $this->successfulStatuses)
                        ->whereBetween('created_at', [$this->startDate, $this->endDate]);
                }
            ])
            ->having('successful_count', '>', 1)
            ->count();

        $repeatRate = $totalBuyers > 0 ? round(($repeatBuyers / $totalBuyers) * 100, 1) : 0;

        // میانگین ارزش طول عمر مشتری (CLV)
        $totalRevenue = Invoice::where('bot_id', $this->botId)
            ->whereIn('status', $this->successfulStatuses)
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->sum(DB::raw('total_amount - discount_amount'));

        $clv = $totalBuyers > 0 ? round($totalRevenue / $totalBuyers) : 0;

        return [
            'total_buyers' => $totalBuyers,
            'repeat_buyers' => $repeatBuyers,
            'repeat_rate' => $repeatRate,
            'clv' => $clv,
        ];
    }

    /**
     * 💤 لیست مشتریان غیرفعال (کسانی که در N روز گذشته خرید نکرده‌اند)
     */
    public function getInactiveCustomers($days = 30, $limit = 5)
    {
        // تاریخی که مرز غیرفعال بودن را مشخص می‌کند
        $threshold = Carbon::now()->subDays($days);

        return User::where('bot_id', $this->botId)
            ->whereHas('invoices', function ($q) {
                $q->whereIn('status', $this->successfulStatuses);
            })
            ->whereDoesntHave('invoices', function ($q) use ($threshold) {
                $q->whereIn('status', $this->successfulStatuses)
                    ->where('created_at', '>=', $threshold);
            })
            ->addSelect([
                'last_purchase_date' => Invoice::select('created_at')
                    ->whereColumn('user_id', 'users.id')
                    ->whereIn('status', $this->successfulStatuses)
                    ->latest()
                    ->limit(1)
            ])
            ->orderBy('last_purchase_date', 'asc')
            ->take($limit)
            ->get();
    }
    /**
     * 📦 تفکیک وضعیت سفارش‌های موفق
     */
    public function getOrderStatusBreakdown(): array
    {
        return Invoice::where('bot_id', $this->botId)
            ->whereIn('status', $this->successfulStatuses)
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    /**
     * 💎 بخش‌بندی مشتریان بر اساس حجم خرید (Segmentation)
     */
    public function getCustomerSegments(): array
    {
        // درآوردن مجموع خرید تمام مشتریانی که حداقل یک سفارش موفق داشته‌اند
        $users = User::where('bot_id', $this->botId)
            ->whereHas('invoices', function ($q) {
                $q->whereIn('status', $this->successfulStatuses)
                    ->whereBetween('created_at', [$this->startDate, $this->endDate]);
            })
            ->addSelect([
                'total_spent' => Invoice::selectRaw('SUM(total_amount - discount_amount)')
                    ->whereColumn('user_id', 'users.id')
                    ->whereIn('status', $this->successfulStatuses)
                    ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ])
            ->get();

        // تعریف دسته‌بندی‌ها (مثلاً VIP بالای 5 میلیون، وفادار بالای 1 میلیون تومان)
        $segments = [
            'vip' => ['count' => 0, 'label' => 'مشتریان VIP', 'color' => 'warning', 'icon' => 'heroicon-m-star'],
            'loyal' => ['count' => 0, 'label' => 'وفادار', 'color' => 'primary', 'icon' => 'heroicon-m-heart'],
            'normal' => ['count' => 0, 'label' => 'معمولی', 'color' => 'info', 'icon' => 'heroicon-m-user'],
        ];

        foreach ($users as $user) {
            if ($user->total_spent >= 5000000) {
                $segments['vip']['count']++;
            } elseif ($user->total_spent >= 1000000) {
                $segments['loyal']['count']++;
            } else {
                $segments['normal']['count']++;
            }
        }

        return $segments;
    }

    /**
     * 🛒 آخرین سبدهای رها شده (پرداخت نشده)
     */
    public function getAbandonedCartsList($limit = 5)
    {
        return Invoice::where('bot_id', $this->botId)
            ->where('status', 'pending')
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->with('user')
            ->orderBy('updated_at', 'desc')
            ->take($limit)
            ->get();
    }

    /**
     * 📦 آمارهای کلی و ارزش انبار
     */
    public function getInventoryStats(): array
    {
        $totalProducts = \App\Models\Product::where('bot_id', $this->botId)->where('is_active', true)->count();
        $outOfStock = \App\Models\Product::where('bot_id', $this->botId)->where('is_active', true)->where('stock', 0)->count();
        $lowStock = \App\Models\Product::where('bot_id', $this->botId)->where('is_active', true)->whereBetween('stock', [1, 5])->count();

        // محاسبه ارزش کل انبار (تعداد موجودی * قیمت کالا)
        $inventoryValue = \App\Models\Product::where('bot_id', $this->botId)
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->sum(DB::raw('stock * price'));

        return [
            'total_products' => $totalProducts,
            'out_of_stock' => $outOfStock,
            'low_stock' => $lowStock,
            'inventory_value' => $inventoryValue,
        ];
    }

    /**
     * 🚨 لیست کالاهای رو به اتمام و ناموجود
     */
    public function getLowStockProducts($limit = 6)
    {
        return \App\Models\Product::where('bot_id', $this->botId)
            ->where('is_active', true)
            ->where('stock', '<=', 5)
            ->orderBy('stock', 'asc')
            ->take($limit)
            ->get();
    }

    /**
     * 📉 کالاهای راکد (موجود در انبار اما بدون فروش در بازه انتخابی)
     */
    public function getStagnantProducts($limit = 6)
    {
        // ابتدا آیدی محصولاتی که در این بازه فروخته شده‌اند را درمی‌آوریم
        $soldProductIds = InvoiceItem::whereHas('invoice', function ($q) {
            $q->whereIn('status', $this->successfulStatuses)
                ->whereBetween('created_at', [$this->startDate, $this->endDate]);
        })
            ->where('bot_id', $this->botId)
            ->pluck('product_id')
            ->unique();

        // حالا محصولاتی را می‌گیریم که موجودی دارند اما جزو فروخته‌شده‌ها نیستند!
        return \App\Models\Product::where('bot_id', $this->botId)
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->whereNotIn('id', $soldProductIds)
            ->orderByDesc('stock') // محصولاتی که الکی بیشترین فضا را اشغال کرده‌اند
            ->take($limit)
            ->get();
    }
}
