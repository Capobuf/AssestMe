<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('findings', function (Blueprint $table): void {
            $table->dropColumn([
                'recommended_solution_summary',
                'priority',
                'effort',
                'estimate_type',
                'estimate_notes',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('findings', function (Blueprint $table): void {
            $table->text('recommended_solution_summary')->nullable();
            $table->string('priority', 32)->nullable();
            $table->string('effort', 32)->nullable();
            $table->string('estimate_type', 32)->nullable();
            $table->text('estimate_notes')->nullable();
        });
    }
};
