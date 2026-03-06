<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('panel_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('panel_name')->default('Meu Painel');
            $table->string('panel_url');
            $table->text('api_key_encrypted');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_verified_at')->nullable();
            $table->enum('status', ['connected', 'error', 'untested'])->default('untested');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('panel_configs');
    }
};
