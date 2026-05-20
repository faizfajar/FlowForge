<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('step_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workflow_run_id');
            $table->string('step_id');
            $table->string('step_type');
            $table->string('status');
            $table->jsonb('input')->nullable();
            $table->jsonb('output')->nullable();
            $table->text('error')->nullable();
            $table->integer('attempt')->default(1);
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('workflow_run_id')->references('id')->on('workflow_runs')->cascadeOnDelete();
            $table->index(['workflow_run_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('step_runs');
    }
};
