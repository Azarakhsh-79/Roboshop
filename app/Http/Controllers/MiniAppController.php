<?php

namespace App\Http\Controllers; // با توجه به پوشه Shop

use App\Http\Controllers\Controller;
use App\Models\ManagedBot;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MiniAppController extends Controller
{

    public function index($bot_username, Request $request)
    {
        $bot = ManagedBot::where('bot_username', $bot_username)
            ->where('status', 'active')
            ->firstOrFail();

        $botAvatar = $this->getBotAvatar($bot);

        $categories = \App\Models\Category::where('bot_id', $bot->id)
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        // اسلایدر بالا (میکس جدیدترین‌ها و پربازدیدها)
        $sliderProducts = Product::where('bot_id', $bot->id)
            ->where('is_active', true)
            ->with(['category', 'images'])
            ->orderByDesc('views_count') // محصولات پربازدید در هدر
            ->take(5)
            ->get();

        // ۱. پیشنهادهای ویژه (پرفروش‌ترین‌ها بر اساس فیلد sales_count)
        $specialProducts = Product::where('bot_id', $bot->id)
            ->where('is_active', true)
            ->with(['category', 'images'])
            ->orderByDesc('sales_count')
            ->take(6)
            ->get();

        // ۲. جدیدترین محصولات (بر اساس زمان ساخت)
        $latestProducts = Product::where('bot_id', $bot->id)
            ->where('is_active', true)
            ->with(['category', 'images'])
            ->latest()
            ->take(6)
            ->get();

        // ۳. محصولات تخفیف‌دار (دارای discount_price معتبر)
        $discountedProducts = Product::where('bot_id', $bot->id)
            ->where('is_active', true)
            ->whereNotNull('discount_price')
            ->where('discount_price', '>', 0)
            ->whereColumn('discount_price', '<', 'price') // فقط اگر تخفیف واقعا کمتر از قیمت اصلی باشد
            ->with(['category', 'images'])
            ->latest()
            ->take(6)
            ->get();

        return view('miniapp.index', compact(
            'bot',
            'categories',
            'sliderProducts',
            'specialProducts',
            'latestProducts',
            'discountedProducts',
            'botAvatar'
        ));
    }
   // جابجایی جایگاه Request $request و $id = null
    public function category(Request $request, $bot_username, $id = null)
    {
        $bot = ManagedBot::where('bot_username', $bot_username)
            ->where('status', 'active')
            ->firstOrFail();

        if (!$id) {
            $firstCategory = \App\Models\Category::where('bot_id', $bot->id)
                ->whereNull('parent_id')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->first();

            if (!$firstCategory) {
                abort(404, 'هیچ دسته‌بندی یافت نشد.');
            }
            $id = $firstCategory->id;
        }

        // ۱. پیدا کردن دسته‌بندی اصلی انتخاب شده توسط کاربر
        $category = \App\Models\Category::where('bot_id', $bot->id)
            ->where('id', $id)
            ->where('is_active', true)
            ->firstOrFail();

        // ۲. واکشی *تمام* دسته‌های اصلی (برای نمایش در ردیف اول بالای صفحه)
        $mainCategories = \App\Models\Category::where('bot_id', $bot->id)
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        // ۳. واکشی زیردسته‌های دسته‌بندی اصلیِ فعال
        $subcategories = \App\Models\Category::where('bot_id', $bot->id)
            ->where('parent_id', $category->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $activeSubcategory = null;
        $products = collect();

        // ۴. مدیریت انتخاب زیردسته (تب‌ها)
        if ($subcategories->isNotEmpty()) {
            if ($request->has('sub')) {
                $activeSubcategory = $subcategories->where('id', $request->sub)->first();
            }
            if (!$activeSubcategory) {
                $activeSubcategory = $subcategories->first();
            }

            $products = Product::where('bot_id', $bot->id)
                ->where('category_id', $activeSubcategory->id)
                ->where('is_active', true)
                ->with(['images'])
                ->latest()
                ->get();
        } else {
            $products = Product::where('bot_id', $bot->id)
                ->where('category_id', $category->id)
                ->where('is_active', true)
                ->with(['images'])
                ->latest()
                ->get();
        }

        // ارسال $mainCategories به ویو
        return view('miniapp.category', compact('bot', 'category', 'mainCategories', 'subcategories', 'activeSubcategory', 'products'));
    }

    public function show($bot_username, $id)
    {
        // ۱. پیدا کردن ربات
        $bot = ManagedBot::where('bot_username', $bot_username)
            ->where('status', 'active')
            ->firstOrFail();

        // ۲. پیدا کردن محصول به همراه عکس‌ها و تنوع‌های فعال
        $product = Product::where('bot_id', $bot->id)
            ->where('id', $id)
            ->where('is_active', true)
            ->with(['category', 'images', 'variants'])
            ->firstOrFail();

        // 🚀 اضافه شدن یک بازدید به دیتابیس
        $product->increment('views_count');

        // ۳. گرفتن محصولات مشابه (از همان دسته‌بندی) برای پیشنهاد در پایین صفحه
        $similarProducts = Product::where('bot_id', $bot->id)
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id) // خود این محصول نباشد
            ->where('is_active', true)
            ->with('images')
            ->take(4)
            ->get();

        return view('miniapp.product', compact('bot', 'product', 'similarProducts'));
    }
    /**
     * متد کمکی برای گرفتن آواتار ربات (دانلود از تلگرام و ذخیره دائمی روی سرور محلی)
     */
    private function getBotAvatar($bot)
    {
        // اگر در دیتابیس ستونی برای عکس دارید، این خط را از کامنت در بیاورید
        // if (!empty($bot->avatar_url)) return $bot->avatar_url;

        // نکته مهم: نام کلید کش را به bot_avatar_v2 تغییر دادیم تا کش خرابِ دیروز نادیده گرفته شود
        return \Illuminate\Support\Facades\Cache::remember("bot_avatar_v2_{$bot->id}", now()->addDays(7), function () use ($bot) {

            // ۱. بررسی وجود فایل محلی (اگر قبلا دانلود کردیم دیگه به تلگرام درخواست نزنیم)
            $localFileName = "bot_avatars/bot_{$bot->id}.jpg";
            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($localFileName)) {
                return asset('storage/' . $localFileName);
            }

            try {
                // ۲. گرفتن آیدی عددی ربات
                $meResponse = \Illuminate\Support\Facades\Http::timeout(5)->get("https://api.telegram.org/bot{$bot->bot_token}/getMe");

                if ($meResponse->successful()) {
                    $botId = $meResponse->json('result.id');

                    // ۳. گرفتن لیست عکس‌های پروفایل
                    $photosResponse = \Illuminate\Support\Facades\Http::timeout(5)->get("https://api.telegram.org/bot{$bot->bot_token}/getUserProfilePhotos", [
                        'user_id' => $botId,
                        'limit' => 1
                    ]);

                    if ($photosResponse->successful()) {
                        $photos = $photosResponse->json('result.photos');

                        if (!empty($photos) && isset($photos[0][0]['file_id'])) {
                            $fileId = $photos[0][0]['file_id'];

                            // ۴. گرفتن مسیر فایل روی سرور تلگرام
                            $fileResponse = \Illuminate\Support\Facades\Http::timeout(5)->get("https://api.telegram.org/bot{$bot->bot_token}/getFile", [
                                'file_id' => $fileId
                            ]);

                            if ($fileResponse->successful()) {
                                $filePath = $fileResponse->json('result.file_path');
                                if ($filePath) {

                                    // ۵. دانلود عکس از تلگرام
                                    $telegramFileUrl = "https://api.telegram.org/file/bot{$bot->bot_token}/{$filePath}";
                                    $imageContent = \Illuminate\Support\Facades\Http::timeout(10)->get($telegramFileUrl)->body();

                                    // ۶. ذخیره عکس در مسیر storage/app/public/bot_avatars سرور خودمان
                                    \Illuminate\Support\Facades\Storage::disk('public')->put($localFileName, $imageContent);

                                    // بازگرداندن لینک دائمی سایت خودتان
                                    return asset('storage/' . $localFileName);
                                }
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Avatar Fetch Error for Bot {$bot->id}: " . $e->getMessage());
            }

            return null;
        });
    }

    // =========================================================
    // 🔐 سیستم احراز هویت یکپارچه (SSO) و پیامکی مینی‌اپ
    // =========================================================

    /**
     * ورود نامرئی و اتوماتیک از طریق مینی‌اپ تلگرام (SSO)
     */
    public function telegramAuth($bot_username, Request $request)
    {
        $bot = ManagedBot::where('bot_username', $bot_username)->where('status', 'active')->firstOrFail();
        $initData = $request->input('initData');

        if (!$initData)
            return response()->json(['success' => false, 'message' => 'No init data']);

        // ۱. ولیدیشن امنیتی تلگرام
        parse_str($initData, $parsedData);
        $receivedHash = $parsedData['hash'] ?? '';
        unset($parsedData['hash']);
        ksort($parsedData);

        $dataCheckString = [];
        foreach ($parsedData as $key => $value) {
            $dataCheckString[] = $key . '=' . $value;
        }
        $dataCheckString = implode("\n", $dataCheckString);

        $secretKey = hash_hmac('sha256', $bot->bot_token, 'WebAppData', true);
        $calculatedHash = bin2hex(hash_hmac('sha256', $dataCheckString, $secretKey, true));

        // ۲. تایید هویت تلگرام
        if (hash_equals($calculatedHash, $receivedHash)) {
            $tgUser = json_decode($parsedData['user'], true);
            $telegramId = $tgUser['id']; // در دیتابیس شما این همان chat_id است

            // ۳. پیدا کردن کاربر در دیتابیس ربات فعلی
            $user = \App\Models\User::where('chat_id', $telegramId)->where('bot_id', $bot->id)->first();

            if ($user && $user->phone) {
                // کاربر شماره دارد -> ورود موفقیت آمیز
                \Illuminate\Support\Facades\Auth::login($user, true);
                return response()->json(['success' => true, 'status' => 'logged_in']);
            } else {
                // کاربر در ربات هست اما شماره نداده
                return response()->json(['success' => true, 'status' => 'needs_phone']);
            }
        }

        return response()->json(['success' => false, 'message' => 'Invalid Hash'], 403);
    }

    /**
     * نمایش صفحه ورود پیامکی (برای کسانی که از مرورگر کروم/سافاری می‌آیند)
     */
    public function showLogin($bot_username)
    {
        $bot = ManagedBot::where('bot_username', $bot_username)->where('status', 'active')->firstOrFail();
        return view('miniapp.login', compact('bot'));
    }

    /**
     * ارسال پیامک تایید در محیط وب
     */
    public function sendWebOtp(Request $request, $bot_username)
    {
        $request->validate(['phone' => 'required|regex:/^09[0-9]{9}$/']);
        $bot = ManagedBot::where('bot_username', $bot_username)->where('status', 'active')->firstOrFail();

        // پیدا کردن یا ساخت کاربر جدید بر اساس شماره موبایل
        $user = \App\Models\User::firstOrCreate(
            ['bot_id' => $bot->id, 'phone' => $request->phone],
            ['status' => 'active']
        );

        $otp = rand(10000, 99999);
        $user->update([
            'otp_code' => $otp,
            'otp_expires_at' => now()->addMinutes(2)
        ]);

        // ارسال پیامک با سرویس اختصاصی شما
        app(\App\Services\SmsService::class)->sendOtp($request->phone, $otp);

        return response()->json(['success' => true]);
    }

    /**
     * بررسی کد پیامک و لاگین نهایی در وب
     */
    public function verifyWebOtp(Request $request, $bot_username)
    {
        $request->validate(['phone' => 'required', 'code' => 'required']);
        $bot = ManagedBot::where('bot_username', $bot_username)->firstOrFail();

        $user = \App\Models\User::where('bot_id', $bot->id)->where('phone', $request->phone)->first();

        if (!$user || $user->otp_code !== $request->code || now()->greaterThan($user->otp_expires_at)) {
            return response()->json(['success' => false, 'message' => 'کد نامعتبر یا منقضی شده است']);
        }

        // پاک کردن کد و لاگین کاربر
        $user->update(['otp_code' => null, 'otp_expires_at' => null]);
        \Illuminate\Support\Facades\Auth::login($user, true);

        return response()->json(['success' => true]);
    }

    /**
     * خروج از حساب کاربری
     */
    public function logout($bot_username)
    {
        \Illuminate\Support\Facades\Auth::logout();
        return redirect("/app/{$bot_username}");
    }


    /**
     * نمایش صفحه پروفایل کاربر (تاریخچه سفارشات و کیف پول)
     */
    public function profile($bot_username)
    {
        $bot = \App\Models\ManagedBot::where('bot_username', $bot_username)
            ->where('status', 'active')
            ->firstOrFail();

        $user = auth()->user();

        // واکشی ۵ سفارش آخر کاربر در این فروشگاه
        $recentOrders = \App\Models\Invoice::where('bot_id', $bot->id)
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // تعداد کل سفارش‌های موفق کاربر
        $successfulOrdersCount = \App\Models\Invoice::where('bot_id', $bot->id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['paid', 'processing', 'shipped', 'delivered'])
            ->count();

        return view('miniapp.profile', compact('bot', 'user', 'recentOrders', 'successfulOrdersCount'));
    }
    // =========================================================
    // 🛒 سیستم سبد خرید مینی‌اپلیکیشن
    // =========================================================

    /**
     * نمایش صفحه سبد خرید و محاسبه فاکتور
     */
    public function cart($bot_username)
    {
        $bot = \App\Models\ManagedBot::where('bot_username', $bot_username)->where('status', 'active')->firstOrFail();

        $cartItems = \App\Models\Cart::where('bot_id', $bot->id)
            ->where('user_id', auth()->id())
            ->with(['product', 'variant'])
            ->get();

        $totalOriginal = 0;
        $totalItemsPayable = 0;
        $totalQty = 0;

        foreach ($cartItems as $item) {
            if (!$item->product) {
                $item->delete();
                continue;
            }

            $basePrice = $item->product->price;

            // بررسی اعتبار تخفیف محصول[cite: 1]
            $isProductDiscountValid = $item->product->discount_price > 0 &&
                (empty($item->product->discount_expires_at) || \Carbon\Carbon::parse($item->product->discount_expires_at)->isFuture());
            $discount = $isProductDiscountValid ? $item->product->discount_price : 0;

            if ($item->variant) {
                $basePrice = $item->variant->price > 0 ? $item->variant->price : $basePrice;
                $varExpires = $item->variant->discount_expires_at ?? $item->product->discount_expires_at;
                $isVariantDiscountValid = $item->variant->discount_price > 0 &&
                    (empty($varExpires) || \Carbon\Carbon::parse($varExpires)->isFuture());
                $discount = $isVariantDiscountValid ? $item->variant->discount_price : $discount;
            }

            $finalPrice = $discount > 0 ? $discount : $basePrice;

            $totalOriginal += ($basePrice * $item->quantity);
            $totalItemsPayable += ($finalPrice * $item->quantity);
            $totalQty += $item->quantity;
        }

        // اعمال کد تخفیف[cite: 1]
        $appliedCode = session("applied_discount_{$bot->id}");
        $couponDiscountAmount = 0;
        $payableAfterCoupon = $totalItemsPayable;

        if ($appliedCode) {
            $discountRecord = \App\Models\DiscountCode::where('bot_id', $bot->id)->where('code', $appliedCode)->first();
            if ($discountRecord && $discountRecord->is_active) {
                if ($discountRecord->type === 'percentage') {
                    $couponDiscountAmount = ($payableAfterCoupon * $discountRecord->value) / 100;
                } else {
                    $couponDiscountAmount = $discountRecord->value;
                }
                $couponDiscountAmount = min($couponDiscountAmount, $payableAfterCoupon);
                $payableAfterCoupon -= $couponDiscountAmount;
            } else {
                session()->forget("applied_discount_{$bot->id}");
            }
        }

        // سود کل و هزینه ارسال[cite: 1]
        $totalDiscount = ($totalOriginal - $totalItemsPayable) + $couponDiscountAmount;
        $shippingCost = (int) \App\Models\Setting::where('bot_id', $bot->id)->where('key', 'shipping_cost')->value('value');
        $finalAmount = $payableAfterCoupon + $shippingCost;

        return view('miniapp.cart', compact(
            'bot',
            'cartItems',
            'totalOriginal',
            'totalDiscount',
            'shippingCost',
            'finalAmount',
            'appliedCode',
            'couponDiscountAmount'
        ));
    }

    /**
     * افزودن کالا به سبد خرید
     */
    public function addToCart(Request $request, $bot_username)
    {
        $bot = \App\Models\ManagedBot::where('bot_username', $bot_username)->firstOrFail();

        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1'
        ]);

        $product = \App\Models\Product::findOrFail($request->product_id);
        $variantId = $request->variant_id;

        // بررسی موجودی انبار[cite: 1]
        $maxStock = $product->stock;
        if ($variantId) {
            $variant = \App\Models\ProductVariant::findOrFail($variantId);
            $maxStock = $variant->stock;
        }

        if ($maxStock !== null && $request->quantity > $maxStock) {
            return response()->json(['success' => false, 'message' => "حداکثر موجودی {$maxStock} عدد است."]);
        }

        $cartItem = \App\Models\Cart::firstOrNew([
            'bot_id' => $bot->id,
            'user_id' => auth()->id(),
            'product_id' => $product->id,
            'variant_id' => $variantId
        ]);

        $cartItem->quantity = $cartItem->exists ? ($cartItem->quantity + $request->quantity) : $request->quantity;

        // بررسی مجدد موجودی بعد از جمع با قبلی
        if ($maxStock !== null && $cartItem->quantity > $maxStock) {
            return response()->json(['success' => false, 'message' => "موجودی انبار برای این تعداد کافی نیست."]);
        }

        $cartItem->save();

        return response()->json(['success' => true, 'message' => 'به سبد خرید اضافه شد.']);
    }

    /**
     * تغییر تعداد کالا
     */
    public function updateCart(Request $request, $bot_username)
    {
        $bot = \App\Models\ManagedBot::where('bot_username', $bot_username)->firstOrFail();
        $cartItem = \App\Models\Cart::where('id', $request->cart_id)->where('user_id', auth()->id())->first();

        if (!$cartItem)
            return response()->json(['success' => false, 'message' => 'آیتم یافت نشد']);

        $maxStock = $cartItem->variant ? $cartItem->variant->stock : $cartItem->product->stock;

        if ($maxStock !== null && $request->quantity > $maxStock) {
            return response()->json(['success' => false, 'message' => "حداکثر موجودی {$maxStock} عدد است."]);
        }

        $cartItem->update(['quantity' => $request->quantity]);

        return response()->json(['success' => true]);
    }

    /**
     * حذف یک آیتم
     */
    public function removeCart(Request $request, $bot_username)
    {
        \App\Models\Cart::where('id', $request->cart_id)->where('user_id', auth()->id())->delete();
        return response()->json(['success' => true]);
    }

    /**
     * خالی کردن کل سبد
     */
    public function clearCart(Request $request, $bot_username)
    {
        $bot = \App\Models\ManagedBot::where('bot_username', $bot_username)->firstOrFail();
        \App\Models\Cart::where('bot_id', $bot->id)->where('user_id', auth()->id())->delete();
        session()->forget("applied_discount_{$bot->id}"); // حذف کد تخفیف

        return response()->json(['success' => true]);
    }

    /**
     * اعمال کد تخفیف
     */
    public function applyDiscount(Request $request, $bot_username)
    {
        $bot = \App\Models\ManagedBot::where('bot_username', $bot_username)->firstOrFail();

        $discount = \App\Models\DiscountCode::where('bot_id', $bot->id)
            ->where('code', $request->code)
            ->where('is_active', true)
            ->first();

        if (!$discount) {
            return response()->json(['success' => false, 'message' => 'کد تخفیف نامعتبر است.']);
        }

        if ($discount->expires_at && $discount->expires_at->isPast()) {
            return response()->json(['success' => false, 'message' => 'این کد منقضی شده است.']);
        }

        if ($discount->usage_limit && $discount->usage_count >= $discount->usage_limit) {
            return response()->json(['success' => false, 'message' => 'ظرفیت این کد پر شده است.']);
        }

        // بررسی حداقل مبلغ خرید
        $cartItems = \App\Models\Cart::where('bot_id', $bot->id)->where('user_id', auth()->id())->get();
        if ($cartItems->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'سبد خرید خالی است.']);
        }

        session(["applied_discount_{$bot->id}" => $discount->code]);

        return response()->json(['success' => true, 'message' => 'کد با موفقیت اعمال شد.']);
    }

    public function toggleFavorite(Request $request, $bot_username)
    {
        $bot = \App\Models\ManagedBot::where('bot_username', $bot_username)->firstOrFail();

        $favorite = \App\Models\Favorite::where('bot_id', $bot->id)
            ->where('user_id', auth()->id())
            ->where('product_id', $request->product_id)
            ->first();

        if ($favorite) {
            $favorite->delete();
            return response()->json(['success' => true, 'status' => 'removed']);
        } else {
            \App\Models\Favorite::create([
                'bot_id' => $bot->id,
                'user_id' => auth()->id(),
                'product_id' => $request->product_id,
            ]);
            return response()->json(['success' => true, 'status' => 'added']);
        }
    }

    /**
     * صفحه اختصاصی محصولات شگفت‌انگیز (تخفیف‌دار)
     */
    public function discounts($bot_username)
    {
        $bot = \App\Models\ManagedBot::where('bot_username', $bot_username)
            ->where('status', 'active')
            ->firstOrFail();

        // واکشی محصولاتی که قیمت تخفیف‌دار آن‌ها ثبت شده و کمتر از قیمت اصلی است
        $discountedProducts = \App\Models\Product::where('bot_id', $bot->id)
            ->where('is_active', true)
            ->whereNotNull('discount_price')
            ->where('discount_price', '>', 0)
            ->whereColumn('discount_price', '<', 'price')
            ->with(['images'])
            ->orderBy('updated_at', 'desc')
            ->get();

        return view('miniapp.discounts', compact('bot', 'discountedProducts'));
    }
}