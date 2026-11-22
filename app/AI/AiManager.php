<?php

namespace App\AI;

use App\AI\Providers\DuckDuckGoProvider;
use App\AI\Providers\PollinationsFreeProvider;
use App\AI\Providers\OpenAiCompatibleProvider;

class AiManager
{
    protected array $providers = [];

    public function __construct()
    {
        // ترتیب اولویت درایورها:
        // ۱. اگر کلید اختصاصی در .env بود اول از آن استفاده کن
        // ۲. در غیر این صورت از DuckDuckGo رایگان استفاده کن
        // ۳. اگر DuckDuckGo کپچا داد، خودکار از Pollinations رایگان استفاده کن
        $this->providers = [
            new OpenAiCompatibleProvider(),
            new DuckDuckGoProvider(),
            new PollinationsFreeProvider(),
        ];
    }

    public function chat(array $messages, array $options = []): ?string
    {
        foreach ($this->providers as $provider) {
            $reply = $provider->chat($messages, $options);
            if (!empty($reply)) {
                return $reply;
            }
        }

        return null;
    }
}
