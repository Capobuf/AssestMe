<?php declare(strict_types=1); ?>
<span
    data-assestme-save-status
    data-status="{{ $status }}"
    data-saved-label="{{ __('assestme.workspace.status.saved') }}"
    data-unsaved-label="{{ __('assestme.workspace.status.unsaved') }}"
    data-saving-label="{{ __('assestme.workspace.status.saving') }}"
    data-error-label="{{ __('assestme.workspace.status.error') }}"
    data-conflict-label="{{ __('assestme.workspace.status.conflict') }}"
    data-offline-label="{{ __('assestme.workspace.status.offline') }}"
    data-local-label="{{ __('assestme.workspace.status.local') }}"
    data-stale-label="{{ __('assestme.workspace.status.stale') }}"
    data-storage-error-label="{{ __('assestme.workspace.status.storage_error') }}"
>{{ $label }}</span>
