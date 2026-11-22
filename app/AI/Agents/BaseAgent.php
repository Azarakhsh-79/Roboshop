<?php

namespace App\AI\Agents;

use App\AI\AiManager;
use App\AI\Contracts\AiAgentInterface;
use App\Models\ManagedBot;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

abstract class BaseAgent implements AiAgentInterface
{
    protected ManagedBot $bot;
    protected ?User $user;
    protected AiManager $ai;

    public function __construct(ManagedBot $bot, ?User $user = null)
    {
        $this->bot = $bot;
        $this->user = $user;
        $this->ai = new AiManager();
    }

    /**
     * کلید کش برای ذخیره تاریخچه گفتگوی هر کاربر در هر فروشگاه
     */
    protected function getHistoryCacheKey(): string
    {
        $userId = $this->user?->id ?? 'guest';
        $agentName = class_basename(static::class);
        return "ai_history:{$agentName}:{$this->bot->id}:{$userId}";
    }

    protected function getHistory(): array
    {
        return Cache::get($this->getHistoryCacheKey(), []);
    }

    protected function appendToHistory(string $userMessage, string $assistantReply): void
    {
        $history = $this->getHistory();
        $history[] = ['role' => 'user', 'content' => $userMessage];
        $history[] = ['role' => 'assistant', 'content' => $assistantReply];

        // نگه داشتن فقط ۶ پیام آخر (۳ رفت و برگشت) برای جلوگیری از سنگین شدن پرامپت
        $history = array_slice($history, -6);

        Cache::put($this->getHistoryCacheKey(), $history, now()->addMinutes(30));
    }

    /**
     * استخراج ایمن JSON از متن خروجی مدل هوش مصنوعی
     */
    protected function extractJson(string $rawText): ?array
    {
        $clean = preg_replace('/^```json\s*|\s*```$/i', '', trim($rawText));

        if (preg_match('/\{.*\}/s', $clean, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}
