<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyTelegramWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        // خواندن توکن از کانفیگ تو در تو
        $secretToken = config('telegram.admin.secret_token');
        $receivedToken = $request->header('X-Telegram-Bot-Api-Secret-Token');

        // اگر هدر با توکن ربات مطابقت نداشت، دسترسی را رد کن
        if (!$receivedToken || $receivedToken !== $secretToken) {
            Log::warning('Unauthorized webhook access attempt', [
                'ip' => $request->ip(),
                'header' => $receivedToken,
                'json' => $request->all()
            ]);
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return $next($request);
    }
}
