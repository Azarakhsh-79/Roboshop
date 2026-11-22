<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureShopSessionExists
{
    /**
     * بررسی وجود سشن حیاتی فروشگاه
     */
    public function handle(Request $request, Closure $next): Response
    {
        
        if (auth()->check() && ! session()->has('current_shop_id')) {

            auth()->logout();
            session()->invalidate();
            session()->regenerateToken();

            return redirect()->route('login')
                ->with('error', 'نشست کاری شما به پایان رسیده است. لطفاً مجدداً وارد شوید.');
        }

        return $next($request);
    }
}
