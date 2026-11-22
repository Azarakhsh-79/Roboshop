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
        Schema::create('owner_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->unique()->constrained('managed_bots')->cascadeOnDelete();

            $table->enum('verification_status', ['not_started', 'pending_usable', 'verified', 'rejected'])
                ->default('not_started');

            $table->string('full_name')->nullable();
            $table->string('phone_number', 20)->nullable();
            $table->string('national_id', 11)->nullable();
            $table->string('id_photo_file_id')->nullable();

            $table->text('notes')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('owner_verifications');
        Schema::enableForeignKeyConstraints();
    }
};
