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
        Schema::create('discount_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained('managed_bots')->cascadeOnDelete();

            $table->string('code', 50);
            $table->enum('type', ['percentage', 'fixed_amount']);
            $table->decimal('value', 15, 2);

            $table->integer('usage_limit')->default(1)->nullable();
            $table->integer('usage_count')->default(0);

            $table->unsignedBigInteger('min_purchase_amount')->default(0);

            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(1);
            $table->timestamps();

            // Unique Constraint (برای جلوگیری از تکرار کد تخفیف در یک ربات خاص)
            $table->unique(['bot_id', 'code'], 'bot_id_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('discount_codes');
        Schema::enableForeignKeyConstraints();
    }
};
