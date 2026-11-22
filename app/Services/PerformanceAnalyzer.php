<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class PerformanceAnalyzer
{
    protected string $logPath;

    public function __construct(?string $date = null)
    {
        $date = $date ?? now()->format('Y-m-d');
        $this->logPath = storage_path("logs/performance/performance-{$date}.log");
    }

    public function analyze(): ?array
    {
        if (!File::exists($this->logPath)) {
            return null;
        }

        $handle = fopen($this->logPath, 'r');
        if (!$handle) {
            return null;
        }

        $durations = [];
        $botStats = [];
        $userStats = []; // 👈 ردیابی کاربران برای کشف اسپمرها
        $updateTypes = [];
        $slowRequestsCount = 0;
        $slowThresholdMs = 1000;

        $metrics = [
            'total_requests' => 0,
            'failed_requests' => 0, // 👈 شمارش خطاهای ۵۰۰ (Fatal Errors)
            'total_duration' => 0,
            'total_db_time' => 0,
            'total_queries' => 0,
            'total_slow_queries' => 0, // 👈 شمارش کوئری‌های کند
            'total_tg_time' => 0,
            'total_other_ms' => 0,
            'max_memory' => 0,
            'max_queries' => 0,
            'max_duration' => 0,
        ];

        while (($line = fgets($handle)) !== false) {
            if (!preg_match('/bot_request (\{.*\})/', $line, $matches)) continue;

            $data = json_decode($matches[1], true);
            if (!$data) continue;

            $metrics['total_requests']++;

            $duration = $data['duration_ms'] ?? 0;
            $queries = $data['query_count'] ?? 0;

            // دریافت مقادیر جدید
            $botId = $data['bot_id'] ?? 'ناشناس';
            $chatId = $data['chat_id'] ?? 'unknown';
            $status = $data['status'] ?? 'success';
            $type = $data['update_type'] ?? 'ناشناس';

            if ($status === 'failed') {
                $metrics['failed_requests']++;
            }

            $durations[] = $duration;
            $metrics['total_duration'] += $duration;
            $metrics['total_db_time'] += ($data['query_time_ms'] ?? 0);
            $metrics['total_queries'] += $queries;
            $metrics['total_slow_queries'] += ($data['slow_queries'] ?? 0);
            $metrics['total_tg_time'] += ($data['telegram_time_ms'] ?? 0);
            $metrics['total_other_ms'] += ($data['other_ms'] ?? 0);

            $metrics['max_memory'] = max($metrics['max_memory'], $data['peak_memory_mb'] ?? 0);
            $metrics['max_queries'] = max($metrics['max_queries'], $queries);
            $metrics['max_duration'] = max($metrics['max_duration'], $duration);

            if ($duration >= $slowThresholdMs) {
                $slowRequestsCount++;
            }

            $updateTypes[$type] = ($updateTypes[$type] ?? 0) + 1;

            // آمار بات‌ها (با ثبت تعداد خطا برای هر بات)
            if (!isset($botStats[$botId])) {
                $botStats[$botId] = ['count' => 0, 'total_duration' => 0, 'max_duration' => 0, 'failed' => 0];
            }
            $botStats[$botId]['count']++;
            $botStats[$botId]['total_duration'] += $duration;
            $botStats[$botId]['max_duration'] = max($botStats[$botId]['max_duration'], $duration);
            if ($status === 'failed') $botStats[$botId]['failed']++;

            // شمارش درخواست‌های کاربران (فقط مقادیر معتبر)
            if ($chatId !== 'unknown' && $chatId !== null) {
                $userStats[$chatId] = ($userStats[$chatId] ?? 0) + 1;
            }
        }

        fclose($handle);

        if ($metrics['total_requests'] === 0) return null;

        sort($durations, SORT_NUMERIC);
        uasort($botStats, fn($a, $b) => $b['count'] <=> $a['count']);
        arsort($userStats); // مرتب‌سازی اسپمرها بر اساس بیشترین درخواست

        return [
            'metrics' => $metrics,
            'percentiles' => [
                'p50' => $this->calculatePercentile($durations, 50),
                'p95' => $this->calculatePercentile($durations, 95),
                'p99' => $this->calculatePercentile($durations, 99),
            ],
            'slow_requests' => $slowRequestsCount,
            'update_types' => $updateTypes,
            'top_bots' => array_slice($botStats, 0, 5, true),
            'top_users' => array_slice($userStats, 0, 3, true), // 👈 فقط ۳ کاربر اول (مظنون به اسپم)
        ];
    }

    private function calculatePercentile(array $sortedData, int $percentile): float
    {
        $count = count($sortedData);
        if ($count === 0) return 0;
        $index = ceil(($percentile / 100) * $count) - 1;
        $index = max(0, min($index, $count - 1));
        return $sortedData[$index];
    }
}
