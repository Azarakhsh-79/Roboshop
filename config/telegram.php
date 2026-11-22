<?php

return [
    'admin' => [
        'bot_token'      => env('ADMIN_BOT_TOKEN'),
        'bot_username' => env('ADMIN_BOT_USERNAME'), // یوزرنیم ادمین‌بات، بدون @
        'webhook_secret' => env('WEBHOOK_SECRET'),
        'super_admin_id' => env('SUPER_ADMIN_ID'),
        'webhook_url'    => rtrim(env('APP_URL'), '/') . '/api/bot/admin',
        'secret_token'   => env('SECRETTOKEN'),
    ],

];
