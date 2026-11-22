<?php

namespace App\Logging;

use Illuminate\Support\Facades\Log;

class PerformanceLogger
{
    public static function logBotRequest(
        string $updateType,
        ?int $botId,
        ?string $chatId,
        string $status,
        float $requestStartMicrotime,
        array $queryLog = [],
        float $telegramTimeMs = 0
    ): void {
        $durationMs = round((microtime(true) - $requestStartMicrotime) * 1000, 2);
        $peakMemoryMb = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

        // پردازش زمان کوئری‌ها و شمارش کوئری‌های کند (بیشتر از ۵۰ میلی‌ثانیه)
        $queryTimeMs = 0;
        $slowQueriesCount = 0;

        foreach ($queryLog as $qTime) {
            $queryTimeMs += $qTime;
            if ($qTime > 50) {
                $slowQueriesCount++;
            }
        }

        $queryCount = count($queryLog);
        $otherMs = max(0, round($durationMs - ($queryTimeMs + $telegramTimeMs), 2));

        $logData = [
            'bot_id' => $botId ?? 0,
            'chat_id' => $chatId ?? 'unknown',
            'update_type' => $updateType,
            'status' => $status,
            'duration_ms' => $durationMs,
            'query_count' => $queryCount,
            'slow_queries' => $slowQueriesCount,
            'query_time_ms' => round($queryTimeMs, 2),
            'telegram_time_ms' => round($telegramTimeMs, 2),
            'other_ms' => $otherMs,
            'peak_memory_mb' => $peakMemoryMb,
            'timestamp' => now()->timestamp,
        ];

        Log::channel('performance')->info('bot_request', $logData);
    }
}
