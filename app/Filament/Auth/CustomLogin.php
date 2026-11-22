<?php

namespace App\Filament\Auth;

use Filament\Pages\Auth\Login as BaseLogin;

class CustomLogin extends BaseLogin
{
    // مسیر فایل Blade اختصاصی شما که در مرحله بعد می‌سازیم
    protected static string $view = 'filament.auth.custom-login';
}
