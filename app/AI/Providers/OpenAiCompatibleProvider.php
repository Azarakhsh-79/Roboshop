<?php

namespace App\AI\Providers;

use App\AI\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiCompatibleProvider implements AiProviderInterface
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $model;

    public function __construct()
    {
        // قابل تنظیم در فایل .env برای آینده (مثلا سرویس رایگان Groq یا OpenRouter)
        $this->baseUrl = env('AI_BASE_URL', 'https://api.groq.com/openai/v1/chat/completions');
        $this->apiKey  = env('AI_API_KEY', '');
        $this->model   = env('AI_MODEL', 'llama-3.3-70b-versatile');
    }

    public function chat(array $messages, array $options = []): ?string
    {
        if (empty($this->apiKey)) {
            return null;
        }

        try {
            $response = Http::timeout(15)
                ->withToken($this->apiKey)
                ->post($this->baseUrl, [
                    'model' => $options['model'] ?? $this->model,
                    'messages' => $messages,
                    'temperature' => $options['temperature'] ?? 0.4,
                ]);

            if ($response->successful()) {
                return $response->json('choices.0.message.content');
            }

            return null;
        } catch (\Throwable $e) {
            Log::warning('OpenAiCompatible Provider Error: ' . $e->getMessage());
            return null;
        }
    }
}
