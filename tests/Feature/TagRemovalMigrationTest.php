<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('removes every tag table from the current schema', function (): void {
    expect(Schema::hasTable('finding_tag'))->toBeFalse()
        ->and(Schema::hasTable('finding_template_tag'))->toBeFalse()
        ->and(Schema::hasTable('tags'))->toBeFalse();
});

it('recreates and removes the tag schema coherently on rollback and reapply', function (): void {
    $migration = require database_path('migrations/2026_07_22_000019_remove_tags.php');

    $migration->down();

    expect(Schema::hasTable('finding_tag'))->toBeTrue()
        ->and(Schema::hasTable('finding_template_tag'))->toBeTrue()
        ->and(Schema::hasTable('tags'))->toBeTrue();

    $migration->up();

    expect(Schema::hasTable('finding_tag'))->toBeFalse()
        ->and(Schema::hasTable('finding_template_tag'))->toBeFalse()
        ->and(Schema::hasTable('tags'))->toBeFalse();
});
