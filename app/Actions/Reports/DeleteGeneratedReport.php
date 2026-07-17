<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Actions\Storage\DeleteEntityAccordingToPolicy;
use App\Enums\DeletionPolicy;
use App\Models\DeletionOperation;
use App\Models\GeneratedReport;
use Illuminate\Support\Facades\Cache;
use LogicException;

final readonly class DeleteGeneratedReport
{
    public function __construct(private DeleteEntityAccordingToPolicy $deleteEntity) {}

    public function handle(GeneratedReport $report): DeletionOperation
    {
        return Cache::lock("assessment:{$report->assessment_id}:save", 10)->block(5, function () use ($report): DeletionOperation {
            // The immutable model is deletable only by delegating to the approved recovery protocol.
            $operation = ($this->deleteEntity)(
                $report,
                DeletionPolicy::Permanent,
                [$report->file_path],
            );

            if (! $operation instanceof DeletionOperation) {
                throw new LogicException('Permanent generated report deletion must create a recovery operation.');
            }

            return $operation;
        });
    }
}
