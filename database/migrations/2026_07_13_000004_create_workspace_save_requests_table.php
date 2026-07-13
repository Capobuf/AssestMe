<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_save_requests', function (Blueprint $table): void {
            $table->uuid('request_id')->primary();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('expected_version');
            $table->unsignedInteger('applied_version');
            $table->char('payload_hash', 64);
            $table->json('response');
            $table->timestamp('created_at');
            $table->index(['assessment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_save_requests');
    }
};
