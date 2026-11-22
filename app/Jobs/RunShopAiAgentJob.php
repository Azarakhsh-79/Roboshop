<?php

namespace App\Jobs;

use App\AI\Contracts\AiAgentInterface;
use App\Models\ManagedBot;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunShopAiAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 45;

    protected ManagedBot $bot;
    protected ?User $user;
    protected string $agentClass;
    protected string $userInput;
    protected int|string $chatId;
    protected ?int $waitMessageId;

    public function __construct(
        ManagedBot $bot,
        ?User $user,
        string $agentClass,
        string $userInput,
        int|string $chatId,
        ?int $waitMessageId = null
    ) {
        $this->bot = $bot;
        $this->user = $user;
        $this->agentClass = $agentClass;
        $this->userInput = $userInput;
        $this->chatId = $chatId;
        $this->waitMessageId = $waitMessageId;
    }

    public function handle(): void
    {
        try {
            // نمونه‌سازی داینامیک از کلاس ایجنت (پشتیبانی از هر نوع ایجنت در آینده)
            /** @var AiAgentInterface $agent */
            $agent = new $this->agentClass($this->bot, $this->user);

            $response = $agent->handle($this->userInput);

            $payload = [
                'chat_id' => $this->chatId,
                'text' => $response->text,
                'parse_mode' => 'HTML',
            ];

            if ($response->hasKeyboard()) {
                $payload['reply_markup'] = json_encode($response->getReplyMarkup());
            }

            // اگر پیام «در حال بررسی...» قبلاً ارسال شده، همان را ادیت کن تا صفحه نپرد
            if ($this->waitMessageId) {
                $payload['message_id'] = $this->waitMessageId;
                $res = Http::timeout(10)->post("https://api.telegram.org/bot{$this->bot->bot_token}/editMessageText", $payload);

                if ($res->successful()) {
                    return;
                }
            }

            // در غیر این صورت پیام جدید بفرست
            Http::timeout(10)->post("https://api.telegram.org/bot{$this->bot->bot_token}/sendMessage", $payload);

        } catch (Throwable $e) {
            Log::error("RunShopAiAgentJob Error [Bot {$this->bot->id}]: " . $e->getMessage());

            if ($this->waitMessageId) {
                Http::timeout(5)->post("https://api.telegram.org/bot{$this->bot->bot_token}/editMessageText", [
                    'chat_id' => $this->chatId,
                    'message_id' => $this->waitMessageId,
                    'text' => "🤔 متوجه نشدم!\n\nلطفاً از دکمه‌های منوی اصلی استفاده کنید:",
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [[['text' => '🏠 منوی اصلی', 'callback_data' => 'main_menu']]]
                    ])
                ]);
            }
        }
    }
}
