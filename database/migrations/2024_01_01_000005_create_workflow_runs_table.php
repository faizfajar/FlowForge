<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('workflow_definition_id');
            $table->uuid('workflow_version_id');
            $table->string('status');
            $table->string('trigger_type');
            $table->uuid('triggered_by')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('timeout_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('workflow_definition_id')->references('id')->on('workflow_definitions')->cascadeOnDelete();
            $table->foreign('workflow_version_id')->references('id')->on('workflow_versions')->cascadeOnDelete();
            $table->foreign('triggered_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['tenant_id', 'status']);
            $table->index(['workflow_definition_id', 'created_at']);
        });

        DB::statement("CREATE INDEX workflow_runs_running_partial_index ON workflow_runs (tenant_id, started_at) WHERE status = 'running'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS workflow_runs_running_partial_index');
        Schema::dropIfExists('workflow_runs');
    }
};
