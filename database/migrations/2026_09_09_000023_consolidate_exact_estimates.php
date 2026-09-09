<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('finding_solutions')
            ->where('estimate_type', 'exact')
            ->update(['estimate_type' => 'approximate']);

        DB::table('finding_template_solutions')
            ->where('estimate_type', 'exact')
            ->update(['estimate_type' => 'approximate']);
    }

    public function down(): void
    {
        // The previous application version already supports `approximate`; reversing this
        // consolidation would incorrectly turn genuine estimates into the removed type.
    }
};
