<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyAdminSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        // دریافت سکرت از بادی (Body) یا کوئری استرینگ (URL)
        $secret = $request->input('secret') ?? $request->query('secret');


        if ($secret !== env('WEBHOOK_SECRET', 'azarakhsh1252')) {
            return response()->json([
                'success' => false,
                'message' => 'دسترسی غیرمجاز! Secret نامعتبر است.'
            ], 403);
        }

        return $next($request);
    }
}
