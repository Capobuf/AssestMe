<?php

declare(strict_types=1);

namespace App\Data\Templates;

use App\Models\Finding;
use App\Models\FindingTemplate;

final readonly class FindingTemplateSyncResult
{
    public const CREATED = 'created';

    public const LINKED = 'linked';

    public const UPDATED = 'updated';

    public const ALREADY_ALIGNED = 'already_aligned';

    /**
     * @param  self::CREATED|self::LINKED|self::UPDATED|self::ALREADY_ALIGNED  $outcome
     */
    public function __construct(
        public FindingTemplate $template,
        public Finding $finding,
        public int $appliedVersion,
        public string $outcome,
    ) {}
}
