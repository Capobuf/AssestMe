<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finding_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('external_id', 160)->unique();
            $table->string('title');
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->text('problem');
            $table->text('entrepreneur_notes')->nullable();
            $table->text('technical_notes')->nullable();
            $table->string('default_scope_type', 32);
            $table->text('default_scope_description')->nullable();
            $table->foreignId('default_consequence_level_id')->nullable()->constrained('consequence_levels')->restrictOnDelete();
            $table->foreignId('default_likelihood_level_id')->nullable()->constrained('likelihood_levels')->restrictOnDelete();
            $table->foreignId('default_priority_level_id')->nullable()->constrained('priority_levels')->restrictOnDelete();
            $table->text('priority_rationale')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('finding_template_tag', function (Blueprint $table): void {
            $table->foreignId('finding_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->restrictOnDelete();
            $table->primary(['finding_template_id', 'tag_id']);
        });

        Schema::create('finding_template_solutions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('finding_template_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 160);
            $table->string('title');
            $table->text('description');
            $table->text('comparison_notes')->nullable();
            $table->foreignId('effort_level_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('effort_notes')->nullable();
            $table->string('estimate_type', 32);
            $table->decimal('amount_min', 12, 2)->nullable();
            $table->decimal('amount_max', 12, 2)->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->string('billing_frequency', 32);
            $table->string('custom_billing_frequency', 120)->nullable();
            $table->text('estimate_notes')->nullable();
            $table->boolean('is_recommended')->default(false);
            $table->unsignedInteger('sort_order');
            $table->timestamps();
            $table->softDeletes();
            // Explicit names stay below MySQL and MariaDB's 64-byte identifier limit.
            $table->unique(['finding_template_id', 'external_id'], 'template_solution_external_unique');
            $table->index(['finding_template_id', 'sort_order'], 'template_solution_sort_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finding_template_solutions');
        Schema::dropIfExists('finding_template_tag');
        Schema::dropIfExists('finding_templates');
    }
};
