<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // اضافه کردن وضعیت‌های جدید برای پنل مدیریت به جدول فاکتورها
        DB::statement("ALTER TABLE invoices MODIFY COLUMN status ENUM('pending', 'paid', 'processing', 'shipped', 'delivered', 'canceled', 'failed', 'awaiting_approval') DEFAULT 'pending'");
    }

    public function down(): void
    {
        // بازگشت به حالت قبل (اختیاری)
    }
};
