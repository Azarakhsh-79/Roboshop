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
        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();

            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->boolean('is_super_admin_reply')->default(0);

            $table->text('message');
            $table->string('attachment_file_id', 150)->nullable();
            $table->boolean('is_read')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('support_messages');
        Schema::enableForeignKeyConstraints();
    }
};
