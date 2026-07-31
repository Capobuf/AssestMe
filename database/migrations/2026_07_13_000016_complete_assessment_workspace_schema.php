<?php

declare(strict_types=1);

use App\Enums\BillingFrequency;
use App\Enums\ScopeType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table): void {
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('report_title_override')->nullable();
            $table->string('scope_type', 32)->default(ScopeType::Organization->value);
            $table->text('scope_description')->nullable();
            $table->text('introduction')->nullable();
            $table->text('executive_summary')->nullable();
            $table->text('methodology_notes')->nullable();
            $table->char('locale', 2)->default('it');
        });

        Schema::create('assessment_site', function (Blueprint $table): void {
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->primary(['assessment_id', 'site_id']);
        });

        Schema::table('findings', function (Blueprint $table): void {
            $table->foreignId('source_template_id')->nullable()->constrained('finding_templates')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('technical_notes')->nullable();
            $table->string('scope_type', 32)->default(ScopeType::Organization->value);
            $table->text('scope_description')->nullable();
            $table->foreignId('consequence_level_id')->nullable()->constrained('consequence_levels')->restrictOnDelete();
            $table->foreignId('likelihood_level_id')->nullable()->constrained('likelihood_levels')->restrictOnDelete();
            $table->foreignId('priority_level_id')->nullable()->constrained('priority_levels')->restrictOnDelete();
            $table->boolean('priority_is_overridden')->default(false);
            $table->text('priority_rationale')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
        });

        Schema::create('finding_tag', function (Blueprint $table): void {
            $table->foreignId('finding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->restrictOnDelete();
            $table->primary(['finding_id', 'tag_id']);
        });

        Schema::create('finding_site', function (Blueprint $table): void {
            $table->foreignId('finding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->primary(['finding_id', 'site_id']);
        });

        Schema::create('finding_asset', function (Blueprint $table): void {
            $table->foreignId('finding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->primary(['finding_id', 'asset_id']);
        });

        Schema::create('finding_solutions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('finding_id')->constrained()->cascadeOnDelete();
            $table->string('external_key', 160)->nullable();
            $table->string('title');
            $table->text('description');
            $table->text('comparison_notes')->nullable();
            $table->foreignId('effort_level_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('effort_notes')->nullable();
            $table->string('estimate_type', 32);
            $table->decimal('amount_min', 12, 2)->nullable();
            $table->decimal('amount_max', 12, 2)->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->string('billing_frequency', 32)->default(BillingFrequency::OneOff->value);
            $table->string('custom_billing_frequency', 120)->nullable();
            $table->text('estimate_notes')->nullable();
            $table->unsignedInteger('sort_order');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['finding_id', 'external_key']);
            $table->index(['finding_id', 'sort_order']);
        });

        Schema::table('findings', function (Blueprint $table): void {
            $table->foreignId('recommended_solution_id')->nullable()->constrained('finding_solutions')->restrictOnDelete();
            $table->foreignId('implemented_solution_id')->nullable()->constrained('finding_solutions')->restrictOnDelete();
        });

        Schema::create('evidences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('finding_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('title');
            $table->string('file_path', 1024)->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('original_filename')->nullable();
            $table->text('caption')->nullable();
            $table->text('internal_notes')->nullable();
            $table->boolean('include_in_report')->default(true);
            $table->string('mime_type', 160)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->unsignedInteger('sort_order');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['finding_id', 'sha256']);
            $table->index(['finding_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidences');

        Schema::table('findings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('implemented_solution_id');
            $table->dropConstrainedForeignId('recommended_solution_id');
        });

        Schema::dropIfExists('finding_solutions');
        Schema::dropIfExists('finding_asset');
        Schema::dropIfExists('finding_site');
        Schema::dropIfExists('finding_tag');

        Schema::table('findings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_template_id');
            $table->dropConstrainedForeignId('category_id');
            $table->dropConstrainedForeignId('consequence_level_id');
            $table->dropConstrainedForeignId('likelihood_level_id');
            $table->dropConstrainedForeignId('priority_level_id');
            $table->dropColumn([
                'technical_notes',
                'scope_type',
                'scope_description',
                'priority_is_overridden',
                'priority_rationale',
                'resolution_notes',
                'resolved_at',
            ]);
        });

        Schema::dropIfExists('assessment_site');

        Schema::table('assessments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('client_id');
            $table->dropColumn([
                'report_title_override',
                'scope_type',
                'scope_description',
                'introduction',
                'executive_summary',
                'methodology_notes',
                'locale',
            ]);
        });
    }
};
