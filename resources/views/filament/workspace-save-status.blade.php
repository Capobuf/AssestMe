<?php declare(strict_types=1); ?>
<div class="assestme-save-feedback">
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

    @if (filled($saveError ?? null))
        <p class="assestme-workbench-footer__error" role="alert">{{ $saveError }}</p>
    @endif

    @if ($errors->any())
        <div
            class="assestme-validation-summary"
            role="alert"
            data-dusk="workspace-validation-summary"
            x-init="$nextTick(() => window.dispatchEvent(new CustomEvent('assestme-validation-visible', { detail: { field: @js($saveErrorField ?? null) } })))"
        >
            <strong>{{ __('assestme.workspace.errors.validation_summary') }}</strong>
            <ul>
                @foreach (array_unique($errors->all()) as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
