<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_ai_knowledge', function (Blueprint $table) {
            $table->longText('apps_knowledge')->nullable()->after('system_prompt');
        });
    }

    public function down(): void
    {
        Schema::table('admin_ai_knowledge', function (Blueprint $table) {
            $table->dropColumn('apps_knowledge');
        });
    }
};
