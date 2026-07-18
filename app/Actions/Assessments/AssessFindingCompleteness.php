<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Enums\FindingStatus;
use App\Enums\ScopeType;
use App\Models\Finding;
use Illuminate\Database\Eloquent\Builder;

final readonly class AssessFindingCompleteness
{
    public function __construct(private ValidateAssessmentCompletion $completionValidator) {}

    /** @return list<string> */
    public function __invoke(Finding $finding): array
    {
        $finding->loadMissing(['solutions', 'sites', 'assets']);

        return $this->completionValidator->findingErrors($finding);
    }

    /** @param Builder<Finding> $query
     * @return Builder<Finding>
     */
    public function applyIncompleteFilter(Builder $query): Builder
    {
        return $query
            ->where('include_in_report', true)
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('title')->orWhere('title', '')
                    ->orWhereNull('category_id')
                    ->orWhereNull('problem')->orWhere('problem', '')
                    ->orWhereNull('priority_level_id')
                    ->orWhere(function (Builder $query): void {
                        $query->where('priority_is_overridden', true)
                            ->where(fn (Builder $query): Builder => $query->whereNull('priority_rationale')->orWhere('priority_rationale', ''));
                    })
                    ->orWhereDoesntHave('solutions')
                    ->orWhereNull('recommended_solution_id')
                    ->orWhere(function (Builder $query): void {
                        $query->where('scope_type', ScopeType::SelectedSites)
                            ->whereDoesntHave('sites');
                    })
                    ->orWhere(function (Builder $query): void {
                        $query->where('scope_type', ScopeType::SelectedAssets)
                            ->whereDoesntHave('assets');
                    })
                    ->orWhere(function (Builder $query): void {
                        $query->whereIn('scope_type', [ScopeType::Network, ScopeType::Custom])
                            ->where(fn (Builder $query): Builder => $query->whereNull('scope_description')->orWhere('scope_description', ''));
                    })
                    ->orWhere(function (Builder $query): void {
                        $query->where('status', FindingStatus::Resolved)
                            ->whereNull('implemented_solution_id')
                            ->where(fn (Builder $query): Builder => $query->whereNull('resolution_notes')->orWhere('resolution_notes', ''));
                    });
            });
    }
}
