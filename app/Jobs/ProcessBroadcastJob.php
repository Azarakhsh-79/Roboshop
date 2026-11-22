<?php

namespace App\Jobs;

use App\Models\Broadcast;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ProcessBroadcastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0;
    public $tries = 3;

    protected $broadcast;

    public function __construct(Broadcast $broadcast)
    {
        $this->broadcast = $broadcast;
    }

    public function handle()
    {
        Log::channel('cron')->info("🚀 Broadcast [{$this->broadcast->id}] STARTED.");

        $this->broadcast->load('bot');
        $botToken = $this->broadcast->bot->bot_token;

        $cacheKey = "broadcast_{$this->broadcast->id}_last_user_id";
        $lastProcessedUserId = Cache::get($cacheKey, 0);
        $sentCount = $this->broadcast->sent_count;

        if ($lastProcessedUserId > 0) {
            Log::channel('cron')->warning("🔄 Broadcast [{$this->broadcast->id}] RESUMING from User ID: {$lastProcessedUserId}");
        }

        User::where('bot_id', $this->broadcast->bot_id)
            ->where('id', '>', $lastProcessedUserId)
            ->chunkById(100, function ($users) use ($botToken, &$sentCount, $cacheKey) {

                Log::channel('cron')->info("📦 Broadcast [{$this->broadcast->id}] Processing chunk of " . count($users) . " users.");

                foreach ($users as $user) {
                    try {
                        $response = null;
                        if ($this->broadcast->photo_file_id) {
                            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$botToken}/sendPhoto", [
                                'chat_id' => $user->chat_id,
                                'photo' => url('storage/' . $this->broadcast->photo_file_id),
                                'caption' => $this->broadcast->message,
                                'parse_mode' => 'HTML',
                            ]);
                        } else {
                            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                                'chat_id' => $user->chat_id,
                                'text' => $this->broadcast->message,
                                'parse_mode' => 'HTML',
                            ]);
                        }

                        if ($response && $response->successful()) {
                            $sentCount++;
                        } else {
                            $errorDetail = $response ? $response->body() : 'No Response';
                            Log::channel('cron')->error("❌ Broadcast [{$this->broadcast->id}] Failed for ChatID {$user->chat_id} | Error: {$errorDetail}");
                        }

                        Cache::put($cacheKey, $user->id, now()->addHours(2));
                        usleep(35000);

                    } catch (\Exception $e) {
                        Log::channel('cron')->error("🚨 Broadcast [{$this->broadcast->id}] Exception on ChatID {$user->chat_id}: " . $e->getMessage());
                    }
                }

                $this->broadcast->update(['sent_count' => $sentCount]);
                Log::channel('cron')->info("✅ Broadcast [{$this->broadcast->id}] Progress: {$sentCount} sent so far.");
            });

        // پایان موفقیت آمیز
        $this->broadcast->update([
            'status' => 'completed',
            'total_users' => $sentCount
        ]);

        Cache::forget($cacheKey);
        Log::channel('cron')->info("🏁 Broadcast [{$this->broadcast->id}] COMPLETED. Total sent: {$sentCount}");

        // =================================================================
        // 🚀 ارسال گزارش نظارتی (BCC) به سوپر ادمین
        // =================================================================
        $this->notifySuperAdmin($sentCount);
    }

    /**
     * متد ارسال گزارش به ناظر کل پلتفرم
     */
    private function notifySuperAdmin($sentCount)
    {
        $superAdminChatId = env('SUPERADMIN_CHAT_ID');
        $adminBotToken = config('telegram.admin.bot_token') ?? env('ADMIN_BOT_TOKEN');

        if (!$superAdminChatId || !$adminBotToken) {
            return;
        }

        $botName = $this->broadcast->bot->bot_name ?? 'نامشخص';
        $botUsername = $this->broadcast->bot->bot_username ?? 'ندارد';
        $shortMessage = mb_substr(strip_tags($this->broadcast->message), 0, 150) . (mb_strlen($this->broadcast->message) > 150 ? '...' : '');

        $text = "🚨 <b>گزارش ارسال پیام همگانی (نظارت)</b>\n\n";
        $text .= "🏪 <b>فروشگاه:</b> {$botName} (@{$botUsername})\n";
        $text .= "👥 <b>تعداد دریافت‌کنندگان:</b> {$sentCount} نفر\n";
        $text .= "🕒 <b>زمان پایان ارسال:</b> " . now()->format('Y-m-d H:i') . "\n\n";
        $text .= "📄 <b>متن پیام:</b>\n<i>{$shortMessage}</i>\n\n";

        if ($this->broadcast->photo_file_id) {
            $text .= "📎 <i>(این پیام حاوی تصویر ضمیمه بود)</i>";
        }

        try {
            Http::timeout(10)->post("https://api.telegram.org/bot{$adminBotToken}/sendMessage", [
                'chat_id' => $superAdminChatId,
                'text' => $text,
                'parse_mode' => 'HTML'
            ]);
        } catch (\Exception $e) {
            Log::channel('cron')->error("Failed to send BCC report to Super Admin: " . $e->getMessage());
        }
    }
}
