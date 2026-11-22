<?php

namespace App\AI\Providers;

use App\AI\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DuckDuckGoProvider implements AiProviderInterface
{
    protected string $model = 'gpt-4o-mini';

    public function chat(array $messages, array $options = []): ?string
    {
        try {
            $headers = [
                'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:130.0) Gecko/20100101 Firefox/130.0',
                'Accept' => 'text/event-stream',
                'Origin' => 'https://duckduckgo.com',
                'Referer' => 'https://duckduckgo.com/',
            ];

            // ۱. دریافت توکن x-vqd-4 از DuckDuckGo
            $statusRes = Http::withHeaders(array_merge($headers, ['x-vqd-accept' => '1']))
                ->timeout(6)
                ->get('https://duckduckgo.com/duckchat/v1/status');

            $vqd = $statusRes->header('x-vqd-4');
            if (!$statusRes->successful() || empty($vqd)) {
                return null;
            }

            // ۲. تبدیل پیام‌های سیستمی به فرمت قابل قبول DuckDuckGo (که فقط نقش user و assistant را می‌پذیرد)
            $formattedMessages = [];
            $systemPrompt = '';

            foreach ($messages as $msg) {
                if ($msg['role'] === 'system') {
                    $systemPrompt .= $msg['content'] . "\n\n";
                } else {
                    $content = $msg['content'];
                    if (!empty($systemPrompt) && $msg['role'] === 'user') {
                        $content = "دستورالعمل سیستم:\n" . $systemPrompt . "پیام کاربر:\n" . $content;
                        $systemPrompt = '';
                    }
                    $formattedMessages[] = [
                        'role' => $msg['role'],
                        'content' => $content,
                    ];
                }
            }

            // ۳. ارسال درخواست چت (پاسخ به صورت Event-Stream برمی‌گردد)
            $chatRes = Http::withHeaders(array_merge($headers, [
                'x-vqd-4' => $vqd,
                'Content-Type' => 'application/json',
            ]))
            ->timeout(15)
            ->post('https://duckduckgo.com/duckchat/v1/chat', [
                'model' => $options['model'] ?? $this->model,
                'messages' => $formattedMessages,
            ]);

            if (!$chatRes->successful()) {
                return null;
            }

            // ۴. استخراج و چسباندن تکه متن‌ها از استریم SSE
            $body = $chatRes->body();
            $fullText = '';
            foreach (explode("\n", $body) as $line) {
                $line = trim($line);
                if (str_starts_with($line, 'data: ')) {
                    $jsonStr = substr($line, 6);
                    if ($jsonStr === '[DONE]') {
                        break;
                    }
                    $data = json_decode($jsonStr, true);
                    if (isset($data['message'])) {
                        $fullText .= $data['message'];
                    }
                }
            }

            return !empty(trim($fullText)) ? trim($fullText) : null;

        } catch (\Throwable $e) {
            Log::warning('DuckDuckGo AI Provider Error: ' . $e->getMessage());
            return null;
        }
    }
}
