<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();

            $table->json('features')->nullable(); 

            // فیلد جدید و منعطف برای جایگزینی همه محدودیت‌ها
            // مثلا: {"product_limit": 10, "admin_limit": -1, "storage": 100}
            $table->json('limits')->nullable();

            $table->json('prices')->nullable();

            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('plans');
        Schema::enableForeignKeyConstraints();
    }
};
