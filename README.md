
<div align="center">

# 🤖 RoboShop (روبوشاپ)

**پلتفرم SaaS فروشگاه‌ساز تلگرام، مینی‌اپ اختصاصی و دستیار فروش هوشمند**

[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![Filament](https://img.shields.io/badge/Filament-3.x-FDAE4B?style=for-the-badge&logo=laravel&logoColor=white)](https://filamentphp.com)
[![Telegram Bot](https://img.shields.io/badge/Telegram-Bot%20%26%20Mini%20App-26A5E4?style=for-the-badge&logo=telegram&logoColor=white)](https://core.telegram.org/bots/webapps)

🌐 **وب‌سایت:** [roboshopapp.ir](https://roboshopapp.ir) | 🤖 **ربات سازنده:** [@RoboShop_fatherbot](https://t.me/RoboShop_fatherbot) | 👨‍💻 **توسعه‌دهنده:** [@Amir_soleimani_79](https://t.me/Amir_soleimani_79)

</div>

---

## 📖 درباره پروژه

**RoboShop** یک پلتفرم ابری چندمستاجری (Multi-Tenant SaaS) مبتنی بر Laravel است که به کسب‌وکارها امکان می‌دهد بدون نیاز به دانش برنامه‌نویسی، ربات فروشگاهی تلگرام و **مینی‌اپ (Telegram Mini App)** اختصاصی خود را راه‌اندازی کنند. هر فروشگاه به‌صورت ایزوله با توکن ربات، درگاه پرداخت، پنل مدیریت و دستیار فروش هوشمند خود عمل می‌کند.

---

## ✨ امکانات و قابلیت‌های کلیدی

### 🏪 معماری چندمستاجری و مدیریت ربات‌ها
- **ساخت خودکار فروشگاه از طریق ربات مادر (`Admin Bot`):** ثبت توکن، تنظیم وب‌هوک اختصاصی با امنیت `Secret Token` و راه‌اندازی آنی فروشگاه.
- **دو پنل مجزای Filament:**
  - **پنل مدیریت فروشگاه (`/admin`):** مدیریت محصولات، دسته‌بندی‌ها، تنوع کالا (Variants)، کدهای تخفیف، سفارش‌ها، تیکت‌های پشتیبانی، ارسال پیام همگانی (Broadcast) و گزارش‌های مالی پیشرفته.
  - **پنل سوپرادمین (`/superadmin`):** مدیریت کل فروشگاه‌های ثبت‌شده (`ManagedBots`)، پلن‌های اشتراک (`Plans`)، پرداخت‌های اشتراک و مانیتورینگ وضعیت کل پلتفرم.

### 📱 مینی‌اپ فروشگاهی تلگرام (Telegram Mini App)
- رابط کاربری مدرن و واکنش‌گرا با تم تیره (Dark Mode)، طراحی‌شده با **Tailwind CSS** و **Alpine.js**.
- احراز هویت امن مبتنی بر سشن و داده‌های تلگرام (`EnsureShopSessionExists`).
- اسلایدر تخفیف‌های ویژه، جستجوی لحظه‌ای، فیلتر دسته‌بندی‌ها، سبد خرید پویا و پروفایل کاربری.

### 🧠 دستیار فروش هوشمند (AI Sales Agent)
- معماری ماژولار در لایه `app/AI` با پشتیبانی از چند ارائه‌دهنده (`OpenAiCompatibleProvider`، `PollinationsFreeProvider`، `DuckDuckGoProvider`).
- پردازش غیرهمگام پاسخ‌ها از طریق صف (`RunShopAiAgentJob`) برای پاسخگویی سریع و بدون وقفه به مشتریان فروشگاه.

### 💳 مالی، حسابداری و صدور فاکتور
- محاسبه خودکار سود و فروش، گزارش‌های تحلیلی (`SalesAnalyticsService`) و نمودارهای زنده در داشبورد.
- صدور خودکار فاکتور PDF فارسی با پشتیبانی کامل از فونت‌ها و تقویم شمسی (`laravel-mpdf` و `filament-jalali`).
- کیف پول کاربران، ثبت تراکنش‌ها و مدیریت وضعیت سفارش‌ها.

### ⚙️ اتوماسیون و زمان‌بندی (Cron & Queues)
- یادآوری خودکار سبدهای خرید رهاشده (`AbandonedCartReminder`).
- لغو خودکار فاکتورهای منقضی‌شده و بازگرداندن موجودی انبار (`CancelExpiredInvoices`).
- بررسی روزانه اشتراک فروشگاه‌ها (`CheckSubscriptions`)، انقضای کدهای تخفیف و ارسال گزارش عملکرد روزانه فروشگاه‌ها.

---

## 🏗️ ساختار کلی پروژه

```text
app/
├── AI/               # هسته هوش مصنوعی (Agents, Providers, Contracts, DTOs)
├── Bot/              # منطق پردازش پیام‌های ربات مادر (Admin) و ربات‌های فروشگاهی (Shop)
├── Console/Commands/ # کران‌جاب‌ها و دستورات زمان‌بندی‌شده (یادآوری سبد، بررسی اشتراک و...)
├── Filament/         # پنل‌های مدیریت فروشگاه (Admin) و مدیریت کل پلتفرم (Superadmin)
├── Http/             # کنترلرها، میدلورهای امنیتی وب‌هوک و اندپوینت‌های مینی‌اپ
├── Jobs/             # پردازش‌های پس‌زمینه (ارسال همگانی، تخفیف گروهی، پردازش تصویر، AI)
├── Models/           # مدل‌های Eloquent و روابط چندمستاجری
└── Services/         # سرویس‌های تحلیل فروش، پیامک، پایش عملکرد و همگام‌سازی تلگرام
