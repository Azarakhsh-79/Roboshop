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
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained('managed_bots')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('plan_key', 50);
            $table->string('plan_name', 100);
            $table->unsignedInteger('duration_days');

            $table->unsignedBigInteger('amount');

            $table->string('receipt_file_id', 150);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            $table->bigInteger('approver_chat_id')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('subscription_payments');
        Schema::enableForeignKeyConstraints();
    }
};
