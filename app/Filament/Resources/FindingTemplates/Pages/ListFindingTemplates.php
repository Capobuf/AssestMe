<?php

declare(strict_types=1);

namespace App\Filament\Resources\FindingTemplates\Pages;

use App\Actions\Templates\ExportFindingTemplates;
use App\Actions\Templates\ImportFindingTemplates;
use App\Filament\Resources\FindingTemplates\FindingTemplateResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ListFindingTemplates extends ListRecords
{
    protected static string $resource = FindingTemplateResource::class;

    /** @var list<array{external_id:string,status:string,differences:list<string>}> */
    public array $importPreview = [];

    public ?string $pendingImportPath = null;

    public string $pendingConflictMode = 'replace';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('previewImport')
                ->label(__('assestme.templates.actions.import'))
                ->schema([
                    FileUpload::make('path')->label(__('assestme.templates.fields.json_file'))->disk('local')->directory('imports')->visibility('private')->acceptedFileTypes(['application/json', 'text/json'])->required(),
                    Select::make('conflict_mode')->label(__('assestme.templates.fields.conflict_mode'))->options([
                        'replace' => __('assestme.templates.values.replace'),
                        'skip' => __('assestme.templates.values.skip'),
                    ])->required()->default('replace'),
                ])
                ->action(function (array $data): void {
                    $path = (string) $data['path'];
                    $json = Storage::disk('local')->get($path);
                    $this->importPreview = app(ImportFindingTemplates::class)->preview($json);
                    $this->pendingImportPath = $path;
                    $this->pendingConflictMode = (string) $data['conflict_mode'];
                    $created = collect($this->importPreview)->where('status', 'create')->count();
                    $replaced = collect($this->importPreview)->where('status', 'replace')->count();
                    $unchanged = collect($this->importPreview)->where('status', 'unchanged')->count();
                    $changedFields = collect($this->importPreview)->flatMap(fn (array $row): array => $row['differences'])->unique()->implode(', ');
                    Notification::make()->title(__('assestme.templates.preview.title'))->body(__('assestme.templates.preview.body', compact('created', 'replaced', 'unchanged', 'changedFields')))->info()->persistent()->send();
                }),
            Action::make('applyImport')
                ->label(__('assestme.templates.actions.apply_import'))
                ->visible(fn (): bool => $this->pendingImportPath !== null)
                ->requiresConfirmation()
                ->modalDescription(fn (): string => collect($this->importPreview)->map(
                    static fn (array $row): string => $row['external_id'].' — '.$row['status'].($row['differences'] === [] ? '' : ': '.implode(', ', $row['differences'])),
                )->implode("\n"))
                ->action(function (): void {
                    $path = $this->pendingImportPath;
                    if ($path === null) {
                        return;
                    }
                    $result = app(ImportFindingTemplates::class)(Storage::disk('local')->get($path), $this->pendingConflictMode);
                    Storage::disk('local')->delete($path);
                    $this->pendingImportPath = null;
                    $this->importPreview = [];
                    Notification::make()->title(__('assestme.templates.imported'))->body(__('assestme.templates.import_result', $result))->success()->send();
                }),
            Action::make('exportTemplates')
                ->label(__('assestme.templates.actions.export'))
                ->action(fn (): StreamedResponse => response()->streamDownload(
                    static fn () => print app(ExportFindingTemplates::class)(),
                    'assestme-finding-templates-v1.json',
                    ['Content-Type' => 'application/json; charset=UTF-8'],
                )),
        ];
    }
}
