<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->string('fatture_in_cloud_company_id', 128)->nullable()->after('tax_code');
            $table->string('fatture_in_cloud_client_id', 128)->nullable()->after('fatture_in_cloud_company_id');
            $table->unique(
                ['fatture_in_cloud_company_id', 'fatture_in_cloud_client_id'],
                'clients_fic_company_client_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->dropUnique('clients_fic_company_client_unique');
            $table->dropColumn(['fatture_in_cloud_company_id', 'fatture_in_cloud_client_id']);
        });
    }
};
