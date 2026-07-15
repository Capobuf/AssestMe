<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->string('format', 8);
            $table->unsignedInteger('version');
            $table->string('file_path', 1024);
            $table->string('file_name');
            $table->unsignedBigInteger('file_size_bytes');
            $table->char('file_sha256', 64);
            $table->char('payload_sha256', 64);
            $table->json('payload_snapshot');
            $table->json('settings_snapshot');
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->unique(['assessment_id', 'format', 'version']);
            $table->index(['assessment_id', 'generated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_reports');
    }
};
