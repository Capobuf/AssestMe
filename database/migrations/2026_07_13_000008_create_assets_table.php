<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('asset_type_id')->constrained()->restrictOnDelete();
            $table->string('name')->nullable();
            $table->string('manufacturer', 120)->nullable();
            $table->string('model', 160)->nullable();
            $table->string('hostname', 253)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->char('mac_address', 17)->nullable();
            $table->string('serial_number', 160)->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
