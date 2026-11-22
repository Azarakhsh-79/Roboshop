<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // استفاده از دستورات خام برای تغییرات امن‌تر در صورت وجود دیتا

        // ۱. حل مشکل انبار: تبدیل به Nullable برای پشتیبانی از "موجودی نامحدود"
        DB::statement('ALTER TABLE `products` MODIFY `stock` INT UNSIGNED NULL DEFAULT NULL;');

        Schema::table('products', function (Blueprint $table) {
            // ۲. حذف فیلد تکراری
            if (Schema::hasColumn('products', 'cost_price')) {
                $table->dropColumn('cost_price');
            }

            // ۳. حل مشکل فیلد deleted_at (حذف نوع عددی و اضافه کردن نوع صحیح تاریخ)
            if (Schema::hasColumn('products', 'deleted_at')) {
                $table->dropColumn('deleted_at');
            }

            // ۴. اضافه کردن فیلدهای آماری برای مارکتینگ
            $table->unsignedInteger('sales_count')->default(0)->after('stock')->comment('تعداد فروش موفق');
            $table->unsignedInteger('views_count')->default(0)->after('sales_count')->comment('تعداد بازدید کالا');
        });

        // اضافه کردن مجدد deleted_at با فرمت صحیح (Timestamp)
        Schema::table('products', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['sales_count', 'views_count']);
            $table->unsignedBigInteger('cost_price')->default(0);
        });

        DB::statement('ALTER TABLE `products` MODIFY `stock` INT UNSIGNED NOT NULL DEFAULT 0;');
    }
};
