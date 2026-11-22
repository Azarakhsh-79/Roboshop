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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bot_id')->constrained('managed_bots')->cascadeOnDelete();

            $table->string('key', 50);
            $table->text('value');

            $table->string('description', 255)->nullable();
            $table->string('type', 20)->default('string');
            $table->json('options')->nullable();

            $table->timestamps();

            // ایندکس یونیک برای جلوگیری از تکرار تنظیمات برای یک ربات
            $table->unique(['bot_id', 'key'], 'uk_key_per_bot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('settings');
        Schema::enableForeignKeyConstraints();
    }
};
