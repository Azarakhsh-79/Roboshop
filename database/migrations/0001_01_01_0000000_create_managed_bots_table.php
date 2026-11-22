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
        Schema::create('managed_bots', function (Blueprint $table) {

            $table->id(); 

            $table->unsignedBigInteger('owner_id');
            $table->string('owner_phone', 20)->nullable();

            $table->string('bot_id_string', 50)->unique();
            $table->string('bot_token', 100);

            $table->string('webhook_secret', 100)->nullable();

            $table->string('bot_name', 100);
            $table->string('bot_username', 100)->nullable();

            $table->enum('status', ['active', 'inactive', 'expired', 'pending_webhook'])->default('inactive');
            $table->string('type', 20)->default('regular');
            $table->string('subscription_plan', 50)->default('free');
            $table->timestamp('subscription_expires_at')->nullable();
            $table->timestamp('stats_last_updated_at')->nullable();

            $table->unsignedInteger('total_users')->default(0);
            $table->unsignedInteger('new_users_today')->default(0);
            $table->unsignedInteger('active_users_today')->default(0);
            $table->unsignedInteger('total_products')->default(0);

            $table->integer('new_products_today')->default(0);
            $table->decimal('user_growth_percent', 5, 2)->default(0.00);

            $table->integer('total_orders')->default(0);
            $table->integer('completed_orders')->default(0);
            $table->unsignedInteger('pending_invoices')->default(0);

            $table->unsignedBigInteger('todays_revenue')->default(0);
            $table->unsignedBigInteger('total_revenue')->default(0);
            $table->unsignedBigInteger('avg_user_purchase')->default(0);

            $table->string('top_product', 255)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('managed_bots');
        Schema::enableForeignKeyConstraints();
    }
};
