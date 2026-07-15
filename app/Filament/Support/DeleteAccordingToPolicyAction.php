<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Actions\Storage\DeleteArchivableEntity;
use App\Enums\DeletionOperationStatus;
use App\Enums\DeletionPolicy;
use App\Models\DeletionOperation;
use App\Settings\GeneralSettings;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

final class DeleteAccordingToPolicyAction
{
    public static function make(string $name = 'delete', ?string $successRedirectUrl = null): Action
    {
        return Action::make($name)
            ->label(fn (): string => self::policy() === DeletionPolicy::Permanent
                ? __('assestme.deletion.actions.permanent')
                : __('assestme.deletion.actions.archive'))
            ->modalHeading(fn (): string => self::policy() === DeletionPolicy::Permanent
                ? __('assestme.deletion.confirmation.permanent_heading')
                : __('assestme.deletion.confirmation.archive_heading'))
            ->modalDescription(fn (): string => self::policy() === DeletionPolicy::Permanent
                ? __('assestme.deletion.confirmation.permanent_description')
                : __('assestme.deletion.confirmation.archive_description'))
            ->modalSubmitActionLabel(fn (): string => self::policy() === DeletionPolicy::Permanent
                ? __('assestme.deletion.actions.permanent')
                : __('assestme.deletion.actions.archive'))
            ->successNotificationTitle(fn (): string => self::policy() === DeletionPolicy::Permanent
                ? __('assestme.deletion.notifications.permanently_deleted')
                : __('assestme.deletion.notifications.archived'))
            ->failureNotificationTitle(__('assestme.deletion.notifications.failed'))
            ->failureNotificationBody(__('assestme.deletion.notifications.failed_body'))
            ->successRedirectUrl($successRedirectUrl)
            ->color('danger')
            ->icon(Heroicon::OutlinedTrash)
            ->requiresConfirmation()
            ->keyBindings(['mod+d'])
            ->hidden(static fn (Model $record): bool => method_exists($record, 'trashed') && $record->trashed())
            ->action(function (Action $action, Model $record) use ($successRedirectUrl): void {
                try {
                    $operation = app(DeleteArchivableEntity::class)->handle($record);
                } catch (Throwable $exception) {
                    Log::error('Archivable entity deletion failed.', [
                        'entity_type' => $record::class,
                        'entity_id' => $record->getKey(),
                        'exception' => $exception,
                    ]);
                    $action->failure();

                    return;
                }

                if ($operation instanceof DeletionOperation && $operation->status === DeletionOperationStatus::CleanupFailed) {
                    Notification::make()
                        ->warning()
                        ->title(__('assestme.deletion.notifications.cleanup_pending'))
                        ->body(__('assestme.deletion.notifications.cleanup_pending_body'))
                        ->persistent()
                        ->send();

                    if ($successRedirectUrl !== null) {
                        $action->redirect($successRedirectUrl);
                    }

                    $action->halt();
                }

                $action->success();
            });
    }

    private static function policy(): DeletionPolicy
    {
        return DeletionPolicy::from(app(GeneralSettings::class)->deletion_policy);
    }
}
