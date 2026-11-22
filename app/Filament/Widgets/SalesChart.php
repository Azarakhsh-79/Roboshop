<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class SalesChart extends ChartWidget
{
    protected static bool $isDiscovered = false; // 👈 این خط جادویی را اضافه کنید
    protected static ?string $heading = 'تحلیل فروش و تخفیف‌ها (۷ روز گذشته)';
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $activeFilter = $this->filter;
        $days = $activeFilter ? (int) $activeFilter : 7;

        $netSalesData = [];
        $labels = [];
        $botId = session('current_shop_id');

        $successfulStatuses = ['paid', 'processing', 'shipped', 'delivered'];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);

            $dailyRevenue = Invoice::where('bot_id', $botId)
                ->whereIn('status', $successfulStatuses)
                ->whereDate('created_at', $date)
                ->get()
                ->sum('final_amount');

            $netSalesData[] = $dailyRevenue;

            // 🌟 تبدیل تاریخ به شمسی با استفاده از پکیج Morilog
            $jalaliDate = \Morilog\Jalali\Jalalian::fromCarbon($date);

            // اگر فیلتر روی ۷ روز بود، اسم روز هفته (مثلاً: دوشنبه) را نشان بده
            // اگر بیشتر بود، تاریخ و ماه (مثلاً: 12 شهریور) را نشان بده
            $labels[] = $days <= 7
                ? $jalaliDate->format('l')
                : $jalaliDate->format('d F');
        }

        return [
            'datasets' => [
                [
                    'label' => 'درآمد خالص (تومان)',
                    'data' => $netSalesData,
                    'fill' => 'start',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.2)',
                    'borderColor' => '#10b981',
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
