<?php

declare(strict_types=1);

namespace App\Actions\Evidence;

use App\Actions\Storage\DeleteEntityAccordingToPolicy;
use App\Enums\DeletionPolicy;
use App\Models\Evidence;
use App\Settings\GeneralSettings;

final class DeleteEvidence
{
    public function __construct(private readonly DeleteEntityAccordingToPolicy $deleteEntity) {}

    public function __invoke(Evidence $evidence): void
    {
        $policy = DeletionPolicy::from(app(GeneralSettings::class)->deletion_policy);
        $paths = $evidence->file_path === null ? [] : [$evidence->file_path];
        ($this->deleteEntity)($evidence, $policy, $paths);
    }
}
