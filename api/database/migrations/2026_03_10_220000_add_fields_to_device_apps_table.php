<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_apps', function (Blueprint $table) {
            $table->string('app_code')->nullable()->after('app_name');
            $table->string('ntdown')->nullable()->after('app_url');
            $table->string('downloader')->nullable()->after('ntdown');
            $table->text('agent_instructions')->nullable()->after('setup_instructions');
        });
    }

    public function down(): void
    {
        Schema::table('device_apps', function (Blueprint $table) {
            $table->dropColumn(['app_code', 'ntdown', 'downloader', 'agent_instructions']);
        });
    }
};
