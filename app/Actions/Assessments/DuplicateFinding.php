<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Enums\AssessmentStatus;
use App\Enums\FindingStatus;
use App\Models\Asset;
use App\Models\Finding;
use App\Models\FindingSolution;
use App\Models\Site;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DuplicateFinding
{
    public function __invoke(Finding $source): Finding
    {
        $source->loadMissing(['assessment', 'tags', 'sites', 'assets', 'solutions']);
        if ($source->assessment->status !== AssessmentStatus::Draft) {
            throw ValidationException::withMessages(['assessment' => __('assestme.assessments.errors.read_only')]);
        }

        return DB::transaction(function () use ($source): Finding {
            $copy = $source->replicate([
                'recommended_solution_id',
                'implemented_solution_id',
                'resolution_notes',
                'resolved_at',
                'sort_order',
            ]);
            $copy->status = FindingStatus::Open;
            $copy->sort_order = ((int) $source->assessment->findings()->max('sort_order')) + 1;
            $copy->save();
            $copy->tags()->sync($source->tags->map(static fn (Tag $tag): int => $tag->id)->all());
            $copy->sites()->sync($source->sites->map(static fn (Site $site): int => $site->id)->all());
            $copy->assets()->sync($source->assets->map(static fn (Asset $asset): int => $asset->id)->all());

            /** @var array<int, FindingSolution> $solutionMap */
            $solutionMap = [];
            foreach ($source->solutions as $solution) {
                $newSolution = $solution->replicate();
                $newSolution->finding()->associate($copy);
                $newSolution->save();
                $solutionMap[(int) $solution->getKey()] = $newSolution;
            }

            $recommendedId = $source->recommended_solution_id;
            if ($recommendedId !== null && isset($solutionMap[$recommendedId])) {
                $copy->recommendedSolution()->associate($solutionMap[$recommendedId]);
            }
            $copy->save();

            return $copy->refresh();
        });
    }
}
