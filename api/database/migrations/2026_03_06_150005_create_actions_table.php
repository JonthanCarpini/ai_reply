<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action_type');
            $table->string('label');
            $table->boolean('enabled')->default(true);
            $table->json('params')->nullable();
            $table->text('custom_instructions')->nullable();
            $table->integer('daily_limit')->default(0);
            $table->integer('daily_count')->default(0);
            $table->date('count_reset_date')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'action_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actions');
    }
};
