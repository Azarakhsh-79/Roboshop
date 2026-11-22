<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>فاکتور سفارش {{ $invoice->tracking_code }}</title>
    <style>
        /* نکته: این استایل‌ها عمداً ساده و سازگار با mPDF نوشته شده‌اند (بدون flex/grid/متغیر CSS) */
        body {
            font-family: 'Vazirmatn', 'Tahoma', 'Arial', sans-serif;
            direction: rtl;
            text-align: right;
            font-size: 12px;
            color: #1f2937;
            background-color: #ffffff;
        }
        table { width: 100%; border-collapse: collapse; }
        .plain td { border: none; padding: 0; vertical-align: middle; }
        .ltr { direction: ltr; unicode-bidi: embed; }

        /* ---------- هدر ---------- */
        .hero {
            background-color: #0b0c26;
            border-bottom: 5px solid #19c2d0;
            border-radius: 14px;
            padding: 22px 24px;
            color: #ffffff;
        }
        .eyebrow { font-size: 11px; color: #5eead4; font-weight: bold; margin-bottom: 6px; }
        .shop { font-size: 24px; font-weight: bold; color: #ffffff; }
        .hero-sub { font-size: 11px; color: #a6a6bd; margin-top: 6px; }
        .hero-left { text-align: left; }
        .badge-paid {
            display: inline-block;
            background-color: #10b981;
            color: #ffffff;
            font-size: 11px;
            font-weight: bold;
            padding: 3px 14px;
            border-radius: 20px;
        }
        .meta-label { font-size: 10px; color: #a6a6bd; margin-top: 10px; }
        .meta-value { font-size: 14px; font-weight: bold; color: #ffffff; }

        /* ---------- کارت‌های اطلاعات ---------- */
        .cards { margin-top: 16px; border-collapse: separate; border-spacing: 0; }
        .card {
            width: 49%;
            vertical-align: top;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 14px;
        }
        .card-gap { width: 2%; }
        .card-title { font-size: 10px; font-weight: bold; color: #0e9aa7; margin-bottom: 8px; }
        .card-line { font-size: 12px; line-height: 1.9; color: #334155; }
        .card-line b { color: #0f172a; }
        .muted { color: #94a3b8; }

        /* ---------- جدول اقلام ---------- */
        .section-title { margin: 20px 0 8px; font-size: 13px; font-weight: bold; color: #0f172a; }
        .items { border: 1px solid #e2e8f0; border-collapse: separate; border-spacing: 0; }
        .items th {
            background-color: #0b0c26;
            color: #ffffff;
            padding: 10px 8px;
            text-align: center;
            font-size: 11px;
            font-weight: bold;
        }
        .items td {
            padding: 10px 8px;
            text-align: center;
            font-size: 12px;
            border-bottom: 1px solid #eef2f7;
        }
        .items .item-name { text-align: right; font-weight: bold; color: #0f172a; }
        .row-alt td { background-color: #f8fafc; }
        .qty {
            display: inline-block;
            background-color: #e0f7fa;
            color: #0e7490;
            font-weight: bold;
            padding: 1px 10px;
            border-radius: 10px;
        }
        .total-cell { font-weight: bold; color: #0f172a; }

        /* ---------- خلاصه مالی ---------- */
        .bottom { margin-top: 18px; border-collapse: separate; border-spacing: 0; }
        .bottom-side { width: 44%; vertical-align: top; }
        .bottom-gap { width: 3%; }
        .bottom-main { width: 53%; vertical-align: top; }

        .pay-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 14px;
        }
        .pay-row td { padding: 5px 0; font-size: 11px; border-bottom: 1px dashed #e2e8f0; }
        .pay-label { color: #64748b; }
        .pay-value { text-align: left; font-weight: bold; color: #0f172a; }

        .summary { border: 1px solid #e2e8f0; border-collapse: separate; border-spacing: 0; }
        .summary td { padding: 9px 14px; border-bottom: 1px solid #eef2f7; font-size: 12px; }
        .summary-label { color: #475569; text-align: right; }
        .summary-value { text-align: left; font-weight: bold; color: #0f172a; }
        .text-danger { color: #e11d48; }
        .text-success { color: #059669; }
        .final-row td {
            background-color: #0b0c26;
            border-bottom: none;
            padding: 14px;
        }
        .final-label { color: #ffffff; font-size: 12px; font-weight: bold; text-align: right; }
        .final-value { color: #5eead4; font-size: 18px; font-weight: bold; text-align: left; }
        .final-unit { font-size: 11px; color: #a6a6bd; font-weight: normal; }

        .saving {
            margin-top: 10px;
            background-color: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 10px;
            padding: 9px 14px;
            font-size: 11px;
            color: #047857;
            text-align: center;
            font-weight: bold;
        }

        /* ---------- فوتر ---------- */
        .footer {
            clear: both;
            text-align: center;
            margin-top: 30px;
            padding-top: 14px;
            border-top: 1px dashed #cbd5e1;
            font-size: 11px;
            color: #64748b;
        }
        .footer-thanks { font-size: 12px; font-weight: bold; color: #0f172a; margin-bottom: 4px; }
        .footer-brand { font-size: 10px; color: #94a3b8; margin-top: 6px; }
    </style>
</head>
<body>

   @php
        // استخراج و امن‌سازی متغیرها برای جلوگیری از ارور
        $date = \Morilog\Jalali\Jalalian::fromDateTime($invoice->created_at)->format('Y/m/d - H:i');
        $buyerName = trim(($invoice->user->first_name ?? '') . ' ' . ($invoice->user->last_name ?? '')) ?: 'مشتری عزیز';

        // 👈 این خط اضافه شد تا یوزرنیم تمیز و آماده چاپ شود
        $buyerUsername = $invoice->user->username ? '@' . $invoice->user->username : 'بدون آیدی';

        $shopName = $invoice->bot->settings->firstWhere('key', 'store_name')?->value ?? $invoice->bot->bot_name ?? 'فروشگاه ما';

        $shippingCost = $invoice->user_info['shipping_cost'] ?? 0;
        $productDiscount = $invoice->user_info['product_discount'] ?? 0;
        $couponDiscount = $invoice->user_info['coupon_discount'] ?? 0;
        $walletUsed = $invoice->wallet_amount_used ?? 0;

        // محاسبه نهایی فاکتور
        $finalAmount = $invoice->total_amount - $invoice->discount_amount + $shippingCost - $walletUsed;

        // ---- اضافه‌شده برای ظاهر جدید فاکتور ----
        // تبدیل ارقام انگلیسی به فارسی برای نمایش
        $fa = fn ($v) => strtr((string) $v, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);

        $totalSaving = $productDiscount + $couponDiscount;
        $itemsCount = collect($items)->sum('quantity');

        if (! $refId) {
            $paymentMethod = 'ثبت نشده';
        } elseif ($refId === 'wallet') {
            $paymentMethod = 'کیف پول';
        } elseif ($refId === 'کارت به کارت') {
            $paymentMethod = 'کارت به کارت';
        } else {
            $paymentMethod = 'درگاه پرداخت آنلاین';
        }
    @endphp

    <!-- هدر فاکتور -->
    <div class="hero">
        <table class="plain">
            <tr>
                <td style="text-align: right;">
                    <div class="eyebrow">فاکتور فروش کالا</div>
                    <div class="shop">{{ $shopName }}</div>
                    <div class="hero-sub">مبالغ این فاکتور به تومان است</div>
                </td>
                <td class="hero-left" style="text-align: left;">
                    <div class="badge-paid">پرداخت‌شده</div>
                    <div class="meta-label">شماره فاکتور</div>
                    <div class="meta-value"><span class="ltr">{{ $invoice->tracking_code }}</span></div>
                    <div class="meta-label">تاریخ ثبت</div>
                    <div class="meta-value">{{ $fa($date) }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- اطلاعات خریدار و ارسال -->
    <table class="cards">
        <tr>
            <td class="card">
                <div class="card-title">مشخصات خریدار</div>
                <div class="card-line"><b>{{ $buyerName }}</b></div>
                <div class="card-line muted"><span class="ltr">{{ $buyerUsername }}</span></div>
                <div class="card-line">شماره تماس: <b>{{ isset($address->phone) ? $fa($address->phone) : 'ثبت نشده' }}</b></div>
            </td>
            <td class="card-gap"></td>
            <td class="card">
                <div class="card-title">نشانی تحویل سفارش</div>
                <div class="card-line">{{ $address->address ?? 'ثبت نشده' }}</div>
            </td>
        </tr>
    </table>

    <!-- لیست اقلام -->
    <div class="section-title">اقلام سفارش</div>
    <table class="items">
        <thead>
            <tr>
                <th width="7%">ردیف</th>
                <th width="43%" style="text-align: right;">شرح کالا</th>
                <th width="10%">تعداد</th>
                <th width="20%">قیمت واحد</th>
                <th width="20%">قیمت کل</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $index => $item)
            <tr class="{{ $index % 2 === 1 ? 'row-alt' : '' }}">
                <td>{{ $fa($index + 1) }}</td>
                <td class="item-name">{{ $item->name_at_purchase }}</td>
                <td><span class="qty">{{ $fa($item->quantity) }}</span></td>
                <td>{{ $fa(number_format($item->price)) }}</td>
                <td class="total-cell">{{ $fa(number_format($item->price * $item->quantity)) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- پرداخت و خلاصه مالی -->
    <table class="bottom">
        <tr>
            <td class="bottom-side">
                <div class="pay-card">
                    <div class="card-title">اطلاعات پرداخت</div>
                    <table class="plain">
                        <tr class="pay-row">
                            <td class="pay-label">وضعیت</td>
                            <td class="pay-value text-success">پرداخت‌شده</td>
                        </tr>
                        <tr class="pay-row">
                            <td class="pay-label">روش پرداخت</td>
                            <td class="pay-value">{{ $paymentMethod }}</td>
                        </tr>
                        <tr class="pay-row">
                            <td class="pay-label">تعداد کل اقلام</td>
                            <td class="pay-value">{{ $fa($itemsCount) }}</td>
                        </tr>
                        @if($refId && $refId !== 'کارت به کارت' && $refId !== 'wallet')
                        <tr class="pay-row">
                            <td class="pay-label">کد رهگیری تراکنش</td>
                            <td class="pay-value" style="font-size: 10px;"><span class="ltr">{{ $refId }}</span></td>
                        </tr>
                        @endif
                    </table>
                </div>
            </td>

            <td class="bottom-gap"></td>

            <td class="bottom-main">
                <table class="summary">
                    <tr>
                        <td class="summary-label">جمع قیمت کالاها</td>
                        <td class="summary-value">{{ $fa(number_format($invoice->total_amount)) }}</td>
                    </tr>

                    @if($productDiscount > 0)
                    <tr>
                        <td class="summary-label">تخفیف روی کالاها</td>
                        <td class="summary-value text-danger">- {{ $fa(number_format($productDiscount)) }}</td>
                    </tr>
                    @endif

                    @if($couponDiscount > 0)
                    <tr>
                        <td class="summary-label">کد تخفیف ({{ $invoice->applied_discount_code }})</td>
                        <td class="summary-value text-danger">- {{ $fa(number_format($couponDiscount)) }}</td>
                    </tr>
                    @endif

                    <tr>
                        <td class="summary-label">هزینه بسته‌بندی و ارسال</td>
                        <td class="summary-value">{{ $shippingCost > 0 ? $fa(number_format($shippingCost)) : 'رایگان' }}</td>
                    </tr>

                    @if($walletUsed > 0)
                    <tr>
                        <td class="summary-label">کسر از موجودی کیف پول</td>
                        <td class="summary-value text-success">- {{ $fa(number_format($walletUsed)) }}</td>
                    </tr>
                    @endif

                    <tr class="final-row">
                        <td class="final-label">مبلغ نهایی پرداخت‌شده</td>
                        <td class="final-value">{{ $fa(number_format($finalAmount)) }} <span class="final-unit">تومان</span></td>
                    </tr>
                </table>

                @if($totalSaving > 0)
                <div class="saving">شما در این خرید {{ $fa(number_format($totalSaving)) }} تومان تخفیف گرفتید.</div>
                @endif
            </td>
        </tr>
    </table>

    <div class="footer">
        <div class="footer-thanks">از اینکه {{ $shopName }} را برای خرید انتخاب کردید، صمیمانه سپاسگزاریم.</div>
        <div>این فاکتور به‌صورت خودکار توسط سیستم صادر شده است.</div>
        <div class="footer-brand">تولید شده توسط پلتفرم هوشمند فروشگاهی روبوشاپ</div>
    </div>

</body>
</html>
