<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->integer('messages_received')->default(0);
            $table->integer('messages_sent')->default(0);
            $table->integer('actions_executed')->default(0);
            $table->integer('tokens_used')->default(0);
            $table->integer('tests_created')->default(0);
            $table->integer('renewals_done')->default(0);
            $table->integer('errors_count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_stats');
    }
};
