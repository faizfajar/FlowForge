<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_definitions', function (Blueprint $table): void {
            $table->string('schedule_cron')->nullable()->after('description');
            $table->timestamp('last_scheduled_run_at')->nullable()->after('schedule_cron');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_definitions', function (Blueprint $table): void {
            $table->dropColumn(['schedule_cron', 'last_scheduled_run_at']);
        });
    }
};
