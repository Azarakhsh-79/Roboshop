<?php

namespace App\AI\Contracts;

interface AiProviderInterface
{
    /**
     * ارسال پیام‌ها به مدل هوش مصنوعی و دریافت پاسخ متنی
     *
     * @param array $messages آرایه‌ای از پیام‌ها با فرمت [['role' => 'system|user|assistant', 'content' => '...']]
     * @param array $options تنظیمات اضافی مثل مدل یا temperature
     * @return string|null
     */
    public function chat(array $messages, array $options = []): ?string;
}
