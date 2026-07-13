<?php

declare(strict_types=1);

namespace App\Filament\Resources\RiskProfiles\Pages;

use App\Actions\Risk\SaveRiskProfileConfiguration;
use App\Filament\Resources\RiskProfiles\RiskProfileResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateRiskProfile extends CreateRecord
{
    protected static string $resource = RiskProfileResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveRiskProfileConfiguration::class)->handle(null, $data);
    }
}
