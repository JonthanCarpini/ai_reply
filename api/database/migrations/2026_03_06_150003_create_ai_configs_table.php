<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('provider', ['openai', 'anthropic', 'google'])->default('openai');
            $table->text('api_key_encrypted');
            $table->string('model')->default('gpt-4o-mini');
            $table->float('temperature')->default(0.7);
            $table->integer('max_tokens')->default(500);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_configs');
    }
};
