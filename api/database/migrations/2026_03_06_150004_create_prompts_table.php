<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->default('Principal');
            $table->text('system_prompt');
            $table->text('greeting_message')->nullable();
            $table->text('fallback_message')->nullable();
            $table->text('offline_message')->nullable();
            $table->json('custom_variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('version')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompts');
    }
};
