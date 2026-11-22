<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use Filament\Widgets\ChartWidget;

class OrdersStatusChart extends ChartWidget
{
    protected static ?string $heading = 'وضعیت کل سفارشات فروشگاه';
    protected static ?int $sort = 2; // هم‌ردیف با نمودار فروش
    protected int | string | array $columnSpan = 1; // نصف صفحه

    protected function getData(): array
    {
        $botId = session('current_shop_id');

        $paid = Invoice::where('bot_id', $botId)->where('status', 'paid')->count();
        $processing = Invoice::where('bot_id', $botId)->where('status', 'processing')->count();
        $shipped = Invoice::where('bot_id', $botId)->whereIn('status', ['shipped', 'delivered'])->count();
        $pending = Invoice::where('bot_id', $botId)->where('status', 'pending')->count();
        $canceled = Invoice::where('bot_id', $botId)->whereIn('status', ['canceled', 'failed'])->count();

        return [
            'datasets' => [
                [
                    'label' => 'تعداد سفارش',
                    'data' => [$paid, $processing, $shipped, $pending, $canceled],
                    'backgroundColor' => [
                        '#10b981', // سبز (موفق)
                        '#f59e0b', // زرد (در حال پردازش)
                        '#3b82f6', // آبی (ارسال شده)
                        '#6b7280', // خاکستری (رها شده)
                        '#ef4444', // قرمز (لغو شده)
                    ],
                ],
            ],
            'labels' => ['پرداخت شده', 'در حال پردازش', 'ارسال/تحویل', 'رها شده', 'لغو/ناموفق'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut'; // نمودار دوناتی (دایره‌ای توخالی)
    }
}
