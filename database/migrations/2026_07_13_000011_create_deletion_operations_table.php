<?php

declare(strict_types=1);

use App\Enums\DeletionOperationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deletion_operations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->string('status', 32)->default(DeletionOperationStatus::Staged->value);
            $table->string('trash_path', 1024);
            $table->json('manifest');
            $table->text('error_text')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deletion_operations');
    }
};
