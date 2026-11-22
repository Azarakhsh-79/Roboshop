<?php

namespace App\AI\Providers;

use App\AI\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PollinationsFreeProvider implements AiProviderInterface
{
    public function chat(array $messages, array $options = []): ?string
    {
        try {
            $response = Http::timeout(30)
                ->retry(2, 2000) // تلاش مجدد در صورت خطا (۲ بار با فاصله 2 ثانیه)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post('https://text.pollinations.ai/openai', [
                    'model' => $options['model'] ?? 'openai',
                    'messages' => $messages,
                    'temperature' => $options['temperature'] ?? 0.4,
                ]);

            if ($response->successful()) {
                return $response->json('choices.0.message.content');
            }

            return null;
        } catch (\Throwable $e) {
            Log::warning('Pollinations AI Provider Error: ' . $e->getMessage());
            return null;
        }
    }
}
