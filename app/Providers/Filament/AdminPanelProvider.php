<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;

// اضافه شدن کلاس‌های نویگیشن
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('shop_panel')
            ->sidebarCollapsibleOnDesktop(true)
            ->colors([
                'primary' => Color::Indigo,   // رنگ اصلی دکمه‌ها و منوها (می‌توانید به Color::Blue یا Color::Teal تغییر دهید)
                'danger' => Color::Rose,     // رنگ خطاهای سیستم و حذف
                'success' => Color::Emerald,  // رنگ موفقیت و تایید
                'warning' => Color::Orange,   // رنگ هشدارها
                'info' => Color::Sky,      // رنگ اطلاعات و لینک‌ها
                'gray' => Color::Slate,    // رنگ پس‌زمینه و متون خنثی
            ])

            // 🖋️ ۲. اعمال فونت زیبای فارسی (وزیرمتن) به صورت مستقیم
            ->font('Vazirmatn', 'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700&display=swap')

            // 🏷️ ۳. تنظیم نام و لوگوی فروشگاه در هدر و تب مرورگر
            ->brandName('پنل مدیریت ربات')
            // ->brandLogo(asset('images/logo.png')) // اگر لوگو دارید، این خط را از کامنت در بیاورید و آدرس عکس را بدهید
            // ->brandLogoHeight('3rem') // ارتفاع لوگو
            // ->favicon(asset('images/favicon.ico')) // آیکون تب مرورگر
            
            // -------------- اضافه شدن ساختار سایدبار --------------
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('فروشگاه')
                    ->icon('heroicon-o-shopping-cart'),

                NavigationGroup::make()
                    ->label('کاربران و ارتباطات')
                    ->icon('heroicon-o-user-group'),

                NavigationGroup::make()
                    ->label('تنظیمات سیستم')
                    ->icon('heroicon-o-cog-8-tooth'),
            ])
            // ---------------------------------------------------
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([])
            ->renderHook(
                PanelsRenderHook::TOPBAR_START, // 👈 این هوک عنصر را دقیقاً در سمت راست قرار می‌دهد
                fn(): string => Blade::render('@include("filament.components.topbar-date")')
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE, // 👈 این هوک عنصر را در سمت چپ (کنار پروفایل) قرار می‌دهد
                fn(): string => Blade::render('@include("filament.components.bot-info")')
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,


                \App\Http\Middleware\EnsureShopSessionExists::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
