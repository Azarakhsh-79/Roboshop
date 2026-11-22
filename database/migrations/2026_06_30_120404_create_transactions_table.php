<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            // کلیدهای خارجی
            $table->foreignId('bot_id')->constrained('managed_bots')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();

            // اطلاعات مالی
            $table->unsignedBigInteger('amount');
            $table->string('gateway', 50)->default('zarinpal');

            // شناسه‌های درگاه
            $table->string('reference_id')->nullable()->comment('Authority in Zarinpal');
            $table->string('tracking_code')->nullable()->comment('RefId in Zarinpal');

            // وضعیت و لاگ‌ها
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->string('description')->nullable();
            $table->json('gateway_response')->nullable()->comment('ذخیره پاسخ خام درگاه برای دیباگ');

            $table->timestamps();

            // ایندکس‌ها برای سرعت بالاتر در آمارگیری
            $table->index('status');
            $table->index('reference_id');
            $table->index('tracking_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
