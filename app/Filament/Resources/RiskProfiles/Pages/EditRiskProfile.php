<?php

declare(strict_types=1);

namespace App\Filament\Resources\RiskProfiles\Pages;

use App\Actions\Risk\SaveRiskProfileConfiguration;
use App\Filament\Resources\RiskProfiles\RiskProfileResource;
use App\Models\ConsequenceLevel;
use App\Models\LikelihoodLevel;
use App\Models\RiskMatrixEntry;
use App\Models\RiskProfile;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class EditRiskProfile extends EditRecord
{
    protected static string $resource = RiskProfileResource::class;

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $profile = $this->getRecord();
        if (! $profile instanceof RiskProfile) {
            throw new LogicException('The risk-profile resource received an invalid model.');
        }

        $scored = static fn (ConsequenceLevel|LikelihoodLevel $level): array => [
            'id' => $level->id, 'code' => $level->code, 'label' => $level->label, 'description' => $level->description, 'score' => $level->score,
            'color' => $level->color, 'sort_order' => $level->sort_order, 'is_enabled' => $level->is_enabled,
        ];
        $data['consequences'] = $profile->consequenceLevels->map($scored)->all();
        $data['likelihoods'] = $profile->likelihoodLevels->map($scored)->all();
        $data['priorities'] = $profile->priorityLevels->map(static fn ($level): array => [
            'id' => $level->id, 'code' => $level->code, 'label' => $level->label, 'description' => $level->description, 'color' => $level->color,
            'sort_order' => $level->sort_order, 'is_enabled' => $level->is_enabled,
        ])->all();
        $data['matrix'] = $profile->matrixEntries()->with(['consequenceLevel', 'likelihoodLevel', 'priorityLevel'])->get()
            ->map(static fn (RiskMatrixEntry $entry): array => [
                'consequence_code' => $entry->consequenceLevel->code,
                'likelihood_code' => $entry->likelihoodLevel->code,
                'priority_code' => $entry->priorityLevel->code,
            ])->all();

        return $data;
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof RiskProfile) {
            throw new LogicException('The risk-profile resource received an invalid model.');
        }

        return app(SaveRiskProfileConfiguration::class)->handle($record, $data);
    }
}
