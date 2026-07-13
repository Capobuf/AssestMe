<?php

declare(strict_types=1);

namespace App\Filament\Resources\Assessments\Schemas;

use App\Enums\AssessmentStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AssessmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label(__('assestme.assessments.fields.title'))
                    ->required()
                    ->maxLength(255),
                DatePicker::make('assessment_date')
                    ->label(__('assestme.assessments.fields.date'))
                    ->required()
                    ->default(today()),
                Hidden::make('status')->default(AssessmentStatus::Draft->value),
            ]);
    }
}
