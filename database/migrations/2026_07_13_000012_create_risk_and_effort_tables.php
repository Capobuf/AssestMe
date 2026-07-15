<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('label', 120);
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('consequence_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('risk_profile_id')->constrained()->restrictOnDelete();
            $table->string('code', 40);
            $table->string('label', 120);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('score');
            $table->string('color', 7);
            $table->unsignedInteger('sort_order');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
            $table->unique(['risk_profile_id', 'code']);
            $table->unique(['risk_profile_id', 'score']);
        });

        Schema::create('likelihood_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('risk_profile_id')->constrained()->restrictOnDelete();
            $table->string('code', 40);
            $table->string('label', 120);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('score');
            $table->string('color', 7);
            $table->unsignedInteger('sort_order');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
            $table->unique(['risk_profile_id', 'code']);
            $table->unique(['risk_profile_id', 'score']);
        });

        Schema::create('priority_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('risk_profile_id')->constrained()->restrictOnDelete();
            $table->string('code', 40);
            $table->string('label', 120);
            $table->text('description')->nullable();
            $table->string('color', 7);
            $table->unsignedInteger('sort_order');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
            $table->unique(['risk_profile_id', 'code']);
        });

        Schema::create('risk_matrix_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('risk_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('consequence_level_id')->constrained()->restrictOnDelete();
            $table->foreignId('likelihood_level_id')->constrained()->restrictOnDelete();
            $table->foreignId('priority_level_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(
                ['risk_profile_id', 'consequence_level_id', 'likelihood_level_id'],
                'risk_matrix_profile_consequence_likelihood_unique',
            );
        });

        Schema::create('effort_levels', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('label', 120);
            $table->text('description')->nullable();
            $table->string('color', 7);
            $table->unsignedInteger('sort_order');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('effort_levels');
        Schema::dropIfExists('risk_matrix_entries');
        Schema::dropIfExists('priority_levels');
        Schema::dropIfExists('likelihood_levels');
        Schema::dropIfExists('consequence_levels');
        Schema::dropIfExists('risk_profiles');
    }
};
