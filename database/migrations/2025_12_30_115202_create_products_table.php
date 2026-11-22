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
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bot_id')->constrained('managed_bots')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();

            $table->string('name', 150);
            $table->string('sku', 100)->nullable();
            $table->text('description')->nullable();

            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('purchase_price')->nullable();
            $table->unsignedBigInteger('discount_price')->nullable();
            $table->unsignedBigInteger('cost_price')->default(0);

            $table->unsignedInteger('stock')->default(0);
            $table->decimal('average_rating', 3, 2)->default(0.00);
            $table->integer('review_count')->default(0);

            $table->timestamp('discount_expires_at')->nullable();
            $table->boolean('is_active')->default(1);
            $table->unsignedBigInteger('channel_message_id')->nullable();

            $table->timestamps();

            // ایندکس‌ها برای جستجوهای سریع
            $table->index('discount_price', 'idx_discount_price');
            $table->fullText(['name', 'description'], 'idx_name_description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('products');
        Schema::enableForeignKeyConstraints();
    }
};
