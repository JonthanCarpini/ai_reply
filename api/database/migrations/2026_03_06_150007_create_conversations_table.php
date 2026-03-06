<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('contact_phone', 20);
            $table->string('contact_name')->nullable();
            $table->string('whatsapp_number', 20)->nullable();
            $table->enum('status', ['active', 'archived', 'blocked'])->default('active');
            $table->integer('message_count')->default(0);
            $table->integer('actions_executed')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'contact_phone']);
            $table->index('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
