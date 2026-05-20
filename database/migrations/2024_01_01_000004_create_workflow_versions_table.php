<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workflow_definition_id');
            $table->integer('version_number');
            $table->jsonb('dag');
            $table->boolean('is_active')->default(false);
            $table->uuid('created_by');
            $table->timestamps();

            $table->foreign('workflow_definition_id')->references('id')->on('workflow_definitions')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->index(['workflow_definition_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_versions');
    }
};
