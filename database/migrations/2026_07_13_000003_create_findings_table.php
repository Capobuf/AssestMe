<?php

declare(strict_types=1);

use App\Enums\FindingStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('findings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->text('problem')->nullable();
            $table->text('entrepreneur_notes')->nullable();
            $table->text('recommended_solution_summary')->nullable();
            $table->string('priority', 32)->nullable();
            $table->string('effort', 32)->nullable();
            $table->string('estimate_type', 32)->nullable();
            $table->text('estimate_notes')->nullable();
            $table->string('status', 32)->default(FindingStatus::Open->value);
            $table->boolean('include_in_report')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['assessment_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('findings');
    }
};
