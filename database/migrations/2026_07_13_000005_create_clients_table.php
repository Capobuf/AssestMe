<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table): void {
            $table->id();
            $table->string('legal_name');
            $table->string('trade_name')->nullable();
            $table->string('vat_number', 32)->nullable()->index();
            $table->string('tax_code', 32)->nullable()->index();
            $table->string('email', 254)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('website', 2048)->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('province', 100)->nullable();
            $table->char('country', 2)->default('IT');
            $table->string('logo_path', 1024)->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
