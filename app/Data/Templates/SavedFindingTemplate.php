<?php

declare(strict_types=1);

namespace App\Data\Templates;

use App\Models\FindingTemplate;

final readonly class SavedFindingTemplate
{
    /** @param list<string> $solutionExternalIds */
    public function __construct(
        public FindingTemplate $template,
        public array $solutionExternalIds,
    ) {}
}
