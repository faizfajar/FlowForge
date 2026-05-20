<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('execution_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workflow_run_id');
            $table->uuid('step_run_id')->nullable();
            $table->string('level');
            $table->text('message');
            $table->jsonb('context')->nullable();
            $table->timestampTz('logged_at');

            $table->foreign('workflow_run_id')->references('id')->on('workflow_runs')->cascadeOnDelete();
            $table->foreign('step_run_id')->references('id')->on('step_runs')->nullOnDelete();
            $table->index(['workflow_run_id', 'logged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_logs');
    }
};
