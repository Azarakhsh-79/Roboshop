<?php

namespace App\Http\Controllers;

use Log;
use Illuminate\Http\Request;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function sendOtp(Request $request, SmsService $smsService)
    {
        // ۱. نرمال‌سازی اعداد فارسی به انگلیسی
        $persianNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $englishNumbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $normalizedPhone = str_replace($persianNumbers, $englishNumbers, $request->phone);

        $request->merge(['phone' => $normalizedPhone]);

        $request->validate([
            'type' => 'required|in:login,register',
            'phone' => ['required', 'regex:/^09[0-9]{9}$/'],
            'name' => 'required_if:type,register|string|max:100',
        ], [
            'phone.regex' => 'فرمت شماره موبایل نامعتبر است.',
            'name.required_if' => 'وارد کردن نام و نام خانوادگی برای ثبت‌نام الزامی است.',
            'name.max' => 'نام وارد شده بیش از حد طولانی است.'
        ]);

        $type = $request->type;
        $phone = $request->phone;

        $adminBotId = \App\Models\ManagedBot::where('bot_id_string', 'admin_bot')->value('id');

        // استفاده از آیدی داینامیک به جای عدد ثابت 17
        $user = User::where('phone', $phone)->where('bot_id', $adminBotId)->first();
        // --- منطق بررسی خطاها با پشتیبانی از AJAX ---
        if ($type === 'login' && !$user) {
            throw ValidationException::withMessages([
                'phone' => 'این شماره در سیستم ثبت‌نام نشده است. لطفاً از تب ثبت‌نام اقدام کنید.'
            ]);
        }

        if ($type === 'register' && $user) {
            throw ValidationException::withMessages([
                'phone' => 'این شماره قبلاً ثبت‌نام شده است. لطفاً وارد شوید.'
            ]);
        }

        $code = rand(10000, 99999);

        // تمام اطلاعات را موقتاً در سشن می‌ریزیم (بدون دخالت دیتابیس)
        session([
            'auth_phone' => $phone,
            'auth_type' => $type,
            'auth_name' => $request->input('name'),
            'otp_code' => $code,
            'otp_expires_at' => now()->addMinutes(2)->timestamp
        ]);

        // استفاده از متد پترن برای عبور از بلک‌لیست
        $smsService->sendOtp($phone, $code);

        // اگر درخواست از سمت جاوااسکریپت (AJAX) بود:
        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'redirect' => route('verify')
            ]);
        }

        return redirect()->route('verify');
    }

    public function showVerify()
    {
        if (!session()->has('auth_phone')) {
            return redirect()->route('login');
        }
        return view('auth.verify');
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'code' => ['required', 'digits:5']
        ]);

        if (!session()->has('auth_phone') || !session()->has('otp_code')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'اطلاعات یافت نشد یا منقضی شده است.', 'redirect' => route('login')], 400);
            }
            return redirect()->route('login')->withErrors(['phone' => 'اطلاعات ورود یافت نشد یا منقضی شده است. لطفاً دوباره تلاش کنید.']);
        }

        if (session('otp_code') != $request->code) {
            throw ValidationException::withMessages([
                'code' => 'کد وارد شده اشتباه است.'
            ]);
        }

        if (now()->timestamp > session('otp_expires_at')) {
            session()->forget(['auth_phone', 'auth_type', 'auth_name', 'otp_code', 'otp_expires_at']);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'کد تایید منقضی شده است.', 'redirect' => route('login')], 400);
            }
            return redirect()->route('login')->withErrors(['phone' => 'کد تایید شما منقضی شده است. لطفاً دوباره درخواست دهید.']);
        }

        $adminBotId = \App\Models\ManagedBot::where('bot_id_string', 'admin_bot')->value('id');

        if (session('auth_type') === 'register') {
            // ساخت ردیف جدید و اتصال خودکار آن به ربات مادر
            $user = User::create([
                'phone' => session('auth_phone'),
                'first_name' => session('auth_name'),
                'status' => 'active',
                'bot_id' => $adminBotId // 👈 استفاده از متغیر داینامیک
            ]);
        } else {
            // لاگین کردن فقط روی اکانت‌های متعلق به ربات مادر
            $user = User::where('phone', session('auth_phone'))->where('bot_id', $adminBotId)->first();
        }

        session()->forget(['auth_phone', 'auth_type', 'auth_name', 'otp_code', 'otp_expires_at']);

        Auth::login($user, true);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'redirect' => route('home')
            ]);
        }

        return redirect()->route('home')->with('reg_success', 'شما با موفقیت وارد سیستم شدید.');
    }

    public function logout()
    {
        Auth::logout();
        return redirect()->route('home');
    }
}
