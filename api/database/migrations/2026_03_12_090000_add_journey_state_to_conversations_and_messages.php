<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('journey_stage')->default('new_contact')->after('status');
            $table->string('journey_status')->default('open')->after('journey_stage');
            $table->json('collected_data')->nullable()->after('journey_status');
            $table->json('pending_requirements')->nullable()->after('collected_data');
            $table->string('last_tool_name')->nullable()->after('pending_requirements');
            $table->string('last_tool_status')->nullable()->after('last_tool_name');
            $table->boolean('human_handoff_requested')->default(false)->after('last_tool_status');
            $table->json('customer_flags')->nullable()->after('human_handoff_requested');

            $table->index('journey_stage');
            $table->index('journey_status');
            $table->index('human_handoff_requested');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->json('context_data')->nullable()->after('action_success');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('context_data');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['journey_stage']);
            $table->dropIndex(['journey_status']);
            $table->dropIndex(['human_handoff_requested']);
            $table->dropColumn([
                'journey_stage',
                'journey_status',
                'collected_data',
                'pending_requirements',
                'last_tool_name',
                'last_tool_status',
                'human_handoff_requested',
                'customer_flags',
            ]);
        });
    }
};
