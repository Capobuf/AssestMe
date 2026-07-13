<?php

declare(strict_types=1);

use App\Enums\AssessmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->date('assessment_date');
            $table->string('status', 32)->default(AssessmentStatus::Draft->value);
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
