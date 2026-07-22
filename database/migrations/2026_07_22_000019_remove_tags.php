<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('finding_tag');
        Schema::dropIfExists('finding_template_tag');
        Schema::dropIfExists('tags');
    }

    public function down(): void
    {
        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug', 160)->unique();
            $table->char('color', 7)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('finding_template_tag', function (Blueprint $table): void {
            $table->foreignId('finding_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->restrictOnDelete();
            $table->primary(['finding_template_id', 'tag_id']);
        });

        Schema::create('finding_tag', function (Blueprint $table): void {
            $table->foreignId('finding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->restrictOnDelete();
            $table->primary(['finding_id', 'tag_id']);
        });
    }
};
