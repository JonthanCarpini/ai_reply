<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('panel_configs', function (Blueprint $table) {
            $table->unsignedInteger('default_test_package_id')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('panel_configs', function (Blueprint $table) {
            $table->dropColumn('default_test_package_id');
        });
    }
};
