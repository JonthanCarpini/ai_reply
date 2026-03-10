<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_apps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('device_type'); // lg_tv, samsung_tv, roku_tv, android_tv, tvbox, android_phone, iphone, etc
            $table->string('app_name');
            $table->text('app_url')->nullable();
            $table->text('download_instructions')->nullable();
            $table->text('setup_instructions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0); // Para ordenar apps quando há múltiplos para mesmo device
            $table->timestamps();

            $table->index(['user_id', 'device_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_apps');
    }
};
