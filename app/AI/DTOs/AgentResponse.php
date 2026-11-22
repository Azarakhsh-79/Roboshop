<?php

namespace App\AI\DTOs;

class AgentResponse
{
    public string $text;
    public array $inlineKeyboard;

    public function __construct(string $text, array $inlineKeyboard = [])
    {
        $this->text = $text;
        $this->inlineKeyboard = $inlineKeyboard;
    }

    public function hasKeyboard(): bool
    {
        return !empty($this->inlineKeyboard);
    }

    public function getReplyMarkup(): array
    {
        return ['inline_keyboard' => $this->inlineKeyboard];
    }
}
