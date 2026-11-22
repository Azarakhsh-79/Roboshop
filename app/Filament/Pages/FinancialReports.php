<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Services\SalesAnalyticsService;
use Filament\Forms\Components\DatePicker; // 👈 کلاس بومی فیلامنت
use Filament\Forms\Components\Grid;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;


use Morilog\Jalali\Jalalian as httpJalalian;


class FinancialReports extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?string $navigationLabel = 'گزارشات حرفه‌ای';
    protected static ?string $title = 'داشبورد هوش تجاری (BI)';
    protected static ?string $navigationGroup = 'فروشگاه';
    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.financial-reports';

    public ?string $startDate = null;
    public ?string $endDate = null;
    public string $activeTab = 'overview';

    public function mount(): void
    {
        $this->form->fill([
            'startDate' => now()->subDays(30)->toDateString(),
            'endDate' => now()->toDateString(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(2)->schema([
                    DatePicker::make('startDate')
                        ->label('از تاریخ')
                        ->jalali() // 👈 فراخوانی متد بومی پکیج
                        ->live(),

                    DatePicker::make('endDate')
                        ->label('تا تاریخ')
                        ->jalali()
                        ->live(),
                ])
            ]);
    }
    // 🚀 واکشی دیتا از سرویس بر اساس تب فعال و فیلتر تاریخ
    protected function getViewData(): array
    {
        $botId = session('current_shop_id');
        $analytics = new SalesAnalyticsService($botId, $this->startDate, $this->endDate);

        $data = [];

        // 🧮 محاسبه و فرمت‌بندی تاریخ‌های شمسی برای نمایش به ادمین
        $start = \Carbon\Carbon::parse($this->startDate ?? now()->subDays(30))->startOfDay();
        $end = \Carbon\Carbon::parse($this->endDate ?? now())->endOfDay();
        $diff = $start->diffInDays($end) ?: 1;

        $prevEnd = $start->copy()->subSecond();
        $prevStart = $start->copy()->subDays($diff);

        $data['currentPeriodText'] = 'از ' . httpJalalian::fromCarbon($start)->format('d F Y') . ' تا ' . httpJalalian::fromCarbon($end)->format('d F Y');
        $data['previousPeriodText'] = 'از ' . httpJalalian::fromCarbon($prevStart)->format('d F Y') . ' تا ' . httpJalalian::fromCarbon($prevEnd)->format('d F Y');

        // لود تنبل: فقط دیتای تبی که باز است لود می‌شود
        if ($this->activeTab === 'overview') {
            $data['kpi'] = $analytics->getOverviewKPIs();
            $data['topProducts'] = $analytics->getTopProducts(5);
            $data['topCustomers'] = $analytics->getTopCustomers(5);

            // 👈 این دو خط جدید اضافه شدند
            $data['funnel'] = $analytics->getFunnelAnalytics();
            $data['statusBreakdown'] = $analytics->getOrderStatusBreakdown();
        } elseif ($this->activeTab === 'customers') {
            $data['customerMetrics'] = $analytics->getCustomerMetrics();
            $data['inactiveCustomers'] = $analytics->getInactiveCustomers(30, 5);

            // 👈 این دو متد جدید اضافه شوند
            $data['segments'] = $analytics->getCustomerSegments();
            $data['abandonedCarts'] = $analytics->getAbandonedCartsList(5);
        } elseif ($this->activeTab === 'products') {
            $data['inventoryStats'] = $analytics->getInventoryStats();
            $data['lowStockProducts'] = $analytics->getLowStockProducts(6);
            $data['stagnantProducts'] = $analytics->getStagnantProducts(6);
        }

        return $data;

        return $data;
    }
    public function setTab($tab)
    {
        $this->activeTab = $tab;
    }
}
