<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actions', function (Blueprint $table) {
            $table->json('preconditions')->nullable()->after('custom_instructions');
            $table->json('phase_scope')->nullable()->after('preconditions');
            $table->unsignedTinyInteger('max_tool_steps')->default(1)->after('phase_scope');
        });

        Schema::table('prompts', function (Blueprint $table) {
            $table->json('structured_prompt')->nullable()->after('system_prompt');
            $table->json('reply_policy')->nullable()->after('structured_prompt');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->string('correlation_id')->nullable()->after('context_data');
            $table->json('source_metadata')->nullable()->after('correlation_id');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['correlation_id', 'source_metadata']);
        });

        Schema::table('prompts', function (Blueprint $table) {
            $table->dropColumn(['structured_prompt', 'reply_policy']);
        });

        Schema::table('actions', function (Blueprint $table) {
            $table->dropColumn(['preconditions', 'phase_scope', 'max_tool_steps']);
        });
    }
};
