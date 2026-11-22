<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PanelGatewayController extends Controller
{
    // این متد تصمیم می‌گیرد کاربر را به کجا بفرستد
    public function index()
    {
        $user = Auth::user();

        // 👈 تغییر مهم ۱: ورود مستقیم از ربات
        // اگر کاربر متعلق به یک ربات خاص است (از داخل ربات فروشگاهی لینک را زده)
        if ($user->bot_id) {
            // تنظیم سشن برای پنل فیلامنت
            session(['current_shop_id' => $user->bot_id]);

            // هدایت به پنل (معمولا در فیلامنت مسیر پیش‌فرض /admin است)
            // اگر مسیر پنل شما چیز دیگری است (مثل /admin_panel) آن را تغییر دهید
            return redirect('/shop_panel');
        }

        // --- منطق زیر برای کاربرانی است که مستقیماً از فرم لاگینِ سایت وارد شده‌اند (اکانت مادر) ---
        $shops = $user->shops;

        // اطمینان از اینکه اگر کاربر هیچ رباتی نداشت، ارور نگیریم
        if (!$shops) {
            $shops = collect();
        }

        $shopsCount = $shops->count();

        // حالت اول: اکانت مادر هیچ رباتی ندارد
        if ($shopsCount === 0) {
            return redirect()->route('bot.create')->with('info', 'برای ورود به پنل مدیریت، ابتدا باید ربات فروشگاهی خود را متصل کنید.');
        }

        // حالت دوم: اکانت مادر یک ربات دارد
        if ($shopsCount === 1) {
            $shop = $shops->first();

            // اگر ربات هنوز در تلگرام استارت نشده، او را به صفحه انتخاب بفرست تا پیام تایید را ببیند
            if ($shop->status === 'pending') {
                return view('panel.select-shop', compact('shops'));
            }

            // اگر فعال بود مستقیم برود پنل
            session(['current_shop_id' => $shop->id]);
            return redirect('/admin');
        }

        // حالت سوم: بیش از یک ربات
        return view('panel.select-shop', compact('shops'));
    }

    // این متد زمانی اجرا می‌شود که کاربرِ دارای چند فروشگاه، روی یکی از آن‌ها کلیک کند
    public function select(Request $request, $id)
    {
        $user = Auth::user();

        // با findOrFail امنیت را تامین می‌کنیم تا کاربر نتواند با تغییر آیدی، وارد ربات شخص دیگری شود
        $shop = $user->shops()->findOrFail($id);

        // ذخیره آیدی در سشن
        session(['current_shop_id' => $shop->id]);

        // ورود به پنل
        return redirect('/admin');
    }
}
