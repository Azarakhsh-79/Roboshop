<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Morilog\Jalali\Jalalian;
use App\Services\PerformanceAnalyzer;

class DailyPerformanceReport extends Command
{
    protected $signature = 'shop:performance-report {--date= : تاریخ مشخص برای گزارش (فرمت Y-m-d)}';
    protected $description = 'تحلیل لاگ‌های پرفورمنس، استخراج صدک‌ها و ارسال گزارش هوشمند به سوپرادمین';

    public function handle()
    {
        $date = $this->option('date') ?? now()->format('Y-m-d');
        $this->info("در حال تحلیل عملکرد سیستم برای تاریخ: {$date} ...");

        $analyzer = new PerformanceAnalyzer($date);
        $reportData = $analyzer->analyze();

        if (!$reportData) {
            $this->error("❌ هیچ دیتایی برای تحلیل در این روز یافت نشد.");
            return;
        }

        $metrics = $reportData['metrics'];
        $percentiles = $reportData['percentiles'];
        $slowRequests = $reportData['slow_requests'];
        $topBots = $reportData['top_bots'];
        $topUsers = $reportData['top_users'];
        $totalReqs = $metrics['total_requests'];

        // محاسبات
        $avgDuration = round($metrics['total_duration'] / $totalReqs, 1);
        $avgDbTime = round($metrics['total_db_time'] / $totalReqs, 1);
        $avgQueries = round($metrics['total_queries'] / $totalReqs, 1);
        $avgOtherMs = round($metrics['total_other_ms'] / $totalReqs, 1);

        // محاسبه نرخ خطا (Error Rate)
        $errorRate = round(($metrics['failed_requests'] / $totalReqs) * 100, 2);

        $jalaliDate = Jalalian::fromCarbon(\Carbon\Carbon::parse($date))->format('Y/m/d');

        // ==========================================
        // ساخت قالب پیام برای تلگرام
        // ==========================================
        $text = "📊 <b>RoboShop Performance Monitor</b>\n";
        $text .= "گزارش روزانه عملکرد سرور | {$jalaliDate}\n\n";

        $text .= "🚀 <b>آمار کلی</b>\n";
        $text .= "▪️ کل درخواست‌ها: <b>" . number_format($totalReqs) . "</b>\n";
        $text .= "▪️ خطاهای پردازشی (Crash): <b>" . number_format($metrics['failed_requests']) . "</b> (<code>{$errorRate}%</code>)\n";
        $text .= "▪️ میانگین پاسخ: <code>{$avgDuration} ms</code>\n";
        $text .= "▪️ درخواست‌های کند (>۱ثانیه): <b>{$slowRequests}</b>\n";
        $text .= "▪️ اوج مصرف حافظه: <code>{$metrics['max_memory']} MB</code>\n\n";

        $text .= "⏱ <b>توزیع زمان پاسخگویی</b>\n";
        $text .= "<i>(شاخص | مقدار)</i>\n";
        $text .= "▫️ P50 (معمول): <code>{$percentiles['p50']} ms</code>\n";
        $text .= "▫️ P95 (۵٪ کندتر): <code>{$percentiles['p95']} ms</code>\n";
        $text .= "▫️ P99 (۱٪ کندترین): <code>{$percentiles['p99']} ms</code>\n\n";

        $text .= "🗄 <b>وضعیت دیتابیس (میانگین)</b>\n";
        $text .= "▪️ تعداد کوئری: <code>{$avgQueries}</code>\n";
        $text .= "▪️ زمان اجرای DB: <code>{$avgDbTime} ms</code>\n";
        $text .= "▪️ کوئری‌های بسیار کند (>50ms): <b>" . number_format($metrics['total_slow_queries']) . "</b> عدد\n";
        $text .= "▪️ بیشترین کوئری یک درخواست: <code>{$metrics['max_queries']}</code>\n\n";

        $text .= "🤖 <b>ربات‌های پرترافیک (Top 5)</b>\n";
        if (empty($topBots)) {
            $text .= "<i>بدون داده</i>\n";
        } else {
            foreach ($topBots as $bId => $stats) {
                $botReqs = number_format($stats['count']);
                $botAvg = round($stats['total_duration'] / $stats['count'], 1);
                $botErrors = $stats['failed'];
                $text .= "▪️ ربات #{$bId}: <b>{$botReqs}</b> ریکوئست (<code>{$botAvg}ms</code>) " . ($botErrors > 0 ? "⚠️ {$botErrors} خطا" : "") . "\n";
            }
        }
        $text .= "\n";

        // نمایش کاربران مشکوک به اسپم
        if (!empty($topUsers)) {
            $text .= "👤 <b>کاربران پرمصرف (احتمال اسپم)</b>\n";
            foreach ($topUsers as $uId => $count) {
                $text .= "▪️ کاربر <code>{$uId}</code>: <b>" . number_format($count) . "</b> ریکوئست\n";
            }
            $text .= "\n";
        }

        // ==========================================
        // سیستم هشدار هوشمند (Smart Alerts)
        // ==========================================
        $alerts = [];

        if ($errorRate >= 1)
            $alerts[] = "نرخ خطای سرور به {$errorRate}% رسیده! حتماً لاگ اصلی لاراول را چک کنید.";
        if ($slowRequests > ($totalReqs * 0.05))
            $alerts[] = "بیش از ۵٪ درخواست‌ها کندتر از ۱ ثانیه بودند.";
        if ($percentiles['p95'] > 1500)
            $alerts[] = "زمان پاسخ P95 بحرانی است ({$percentiles['p95']}ms). دیتابیس یا تلگرام تحت فشار است.";
        if ($metrics['max_memory'] > 100)
            $alerts[] = "مصرف رم به مرز خطرناک رسیده است ({$metrics['max_memory']}MB).";

        if (!empty($topUsers)) {
            $topUserCount = reset($topUsers); // گرفتن تعداد درخواست نفر اول
            if ($topUserCount > 1000)
                $alerts[] = "یک کاربر به تنهایی بیش از ۱۰۰۰ درخواست ارسال کرده! خطر حمله DDoS یا اسپم بات وجود دارد.";
        }

        if (count($alerts) > 0) {
            $text .= "🚨 <b>هشدارهای عملکردی (" . count($alerts) . " مورد)</b>\n";
            foreach ($alerts as $alert) {
                $text .= "⚠️ {$alert}\n";
            }
        }

        // ارسال به تلگرام
        $superAdminChatId = env('SUPERADMIN_CHAT_ID');
        $adminBotToken = config('telegram.admin.bot_token') ?? env('ADMIN_BOT_TOKEN');

        if (!$superAdminChatId || !$adminBotToken) {
            $this->error("متغیرهای تلگرام تنظیم نشده‌اند.");
            return;
        }

        Http::timeout(15)->post("https://api.telegram.org/bot{$adminBotToken}/sendMessage", [
            'chat_id' => $superAdminChatId,
            'text' => $text,
            'parse_mode' => 'HTML'
        ]);

        $this->info("✅ گزارش عملکرد پیشرفته با موفقیت به تلگرام ارسال شد.");
    }
}
