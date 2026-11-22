<?php

namespace App\AI\Contracts;

use App\AI\DTOs\AgentResponse;

interface AiAgentInterface
{
    /**
     * اجرای منطق ایجنت روی ورودی کاربر و برگرداندن پاسخ آماده تلگرام
     */
    public function handle(string $input, array $context = []): AgentResponse;
}
