(() => {
    'use strict';

    let dirty = false;
    let activeFindingRow = null;
    let livewireHookRegistered = false;

    const statusElement = () => document.querySelector('[data-assestme-save-status]');

    const showStatus = (status, element = statusElement()) => {
        if (!element) {
            return;
        }

        const label = element.dataset[`${status}Label`];

        if (label) {
            element.textContent = label;
            element.dataset.status = status;
        }
    };

    const workspaceStatusFor = (target) => {
        const livewireComponent = target.closest('[wire\\:id]');
        const element = statusElement();

        return livewireComponent?.contains(element) ? element : null;
    };

    const clearDirtyAfterSave = () => {
        if (statusElement()?.dataset.status === 'saved') {
            dirty = false;
        }
    };

    const registerLivewireHook = () => {
        if (livewireHookRegistered || !window.Livewire?.hook) {
            return;
        }

        livewireHookRegistered = true;
        window.Livewire.hook('morph.updated', clearDirtyAfterSave);
    };

    document.documentElement.dataset.assestmeWorkspaceAsset = 'loaded';

    document.addEventListener('input', (event) => {
        const element = workspaceStatusFor(event.target);

        if (!element) {
            return;
        }

        dirty = true;
        showStatus(navigator.onLine ? 'unsaved' : 'offline', element);
    });

    document.addEventListener('focusin', (event) => {
        activeFindingRow = event.target.closest('.assestme-workspace-findings tbody tr') ?? activeFindingRow;
    });

    document.addEventListener('pointerdown', (event) => {
        activeFindingRow = event.target.closest('.assestme-workspace-findings tbody tr') ?? activeFindingRow;
    });

    document.addEventListener('keydown', (event) => {
        if (document.querySelector('.fi-modal-open')) {
            return;
        }

        const key = event.key.toLowerCase();
        const target = event.ctrlKey || event.metaKey
            ? (key === 's' ? document.querySelector('[data-dusk="save-assessment"]') : null)
            : (event.altKey && key === 'n' ? document.querySelector('[data-dusk="add-finding"]')
                : event.altKey && key === 't' ? document.querySelector('[data-dusk="add-template"]')
                    : event.altKey && key === 'd' ? activeFindingRow?.querySelector('[data-dusk="clone-finding"]') : null);

        if (!target) {
            return;
        }

        event.preventDefault();
        target.click();
    });

    window.addEventListener('offline', () => {
        if (!statusElement()) {
            return;
        }

        dirty = true;
        showStatus('offline');
    });

    window.addEventListener('online', () => {
        if (dirty) {
            showStatus('unsaved');
        }
    });

    window.addEventListener('beforeunload', (event) => {
        if (!dirty) {
            return;
        }

        event.preventDefault();
        event.returnValue = '';
    });

    document.addEventListener('livewire:init', registerLivewireHook, { once: true });
    registerLivewireHook();
})();
