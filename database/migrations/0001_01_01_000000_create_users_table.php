<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // اصلاح: استفاده از constrained به درستی، مشروط بر اینکه managed_bots.id از نوع id() باشد
            $table->foreignId('bot_id')->nullable()->constrained('managed_bots')->cascadeOnDelete();

            $table->unsignedBigInteger('chat_id')->nullable();

            $table->string('phone', 20)->nullable()->unique();
            $table->string('otp_code', 10)->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->rememberToken();
            $table->string('username', 32)->nullable()->index();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('email', 191)->nullable()->unique();

            $table->char('language', 2)->default('fa');
            $table->boolean('is_admin')->default(0);
            $table->string('password')->nullable();

            $table->unsignedBigInteger('last_used_address_id')->nullable(); // تغییر به unsignedBig برای هماهنگی
            $table->text('spam_info')->nullable();

            $table->enum('status', ['active', 'blocked', 'pending', 'baned'])->default('active')->index();
            $table->string('entry_token', 64)->nullable();

            $table->timestamps();

            // کلید یونیک برای جلوگیری از ثبت تکراری کاربر در یک ربات خاص
            $table->unique(['bot_id', 'chat_id'], 'unique_user_per_bot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // اضافه کردن برای جلوگیری از خطای وابستگی کلید خارجی
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('users');
        Schema::enableForeignKeyConstraints();
    }
};
