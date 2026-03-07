<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_ai_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('provider'); // openai, anthropic, google, etc
            $table->text('api_key_encrypted');
            $table->string('model');
            $table->float('temperature')->default(0.7);
            $table->integer('max_tokens')->default(4096);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->integer('ai_generation_limit')->default(5)->after('actions_limit');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_ai_configs');

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('ai_generation_limit');
        });
    }
};
