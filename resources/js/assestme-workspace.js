(() => {
    'use strict';

    let dirty = false;
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

    const isInteractive = (target) => Boolean(target.closest(
        'input, textarea, select, button, a, [role="button"], [role="menu"], [contenteditable="true"]',
    ));

    const enhanceFindingRows = () => {
        document.querySelectorAll('.assestme-finding-row').forEach((row) => {
            row.setAttribute('aria-selected', row.classList.contains('is-selected') ? 'true' : 'false');
            row.tabIndex = 0;

            if (row.dataset.assestmeKeyboardReady === 'true') {
                return;
            }

            row.dataset.assestmeKeyboardReady = 'true';
            row.addEventListener('click', (event) => {
                if (isInteractive(event.target)) {
                    return;
                }

                row.querySelector('[data-dusk="open-finding"]')?.click();
            });
            row.addEventListener('keydown', (event) => {
                if (isInteractive(event.target) || !['Enter', ' '].includes(event.key)) {
                    return;
                }

                event.preventDefault();
                row.click();
            });
        });
    };

    const clearDirtyAfterSave = () => {
        if (statusElement()?.dataset.status === 'saved') {
            dirty = false;
        }

        enhanceFindingRows();
    };

    const registerLivewireHook = () => {
        if (livewireHookRegistered || !window.Livewire?.hook) {
            return;
        }

        livewireHookRegistered = true;
        window.Livewire.hook('morph.updated', clearDirtyAfterSave);
    };

    document.documentElement.dataset.assestmeWorkspaceAsset = 'loaded';
    enhanceFindingRows();

    document.addEventListener('input', (event) => {
        const element = workspaceStatusFor(event.target);

        if (!element) {
            return;
        }

        dirty = true;
        showStatus(navigator.onLine ? 'unsaved' : 'offline', element);
    });

    document.addEventListener('keydown', (event) => {
        if (document.querySelector('.fi-modal-open')) {
            return;
        }

        const key = event.key.toLowerCase();
        const command = event.ctrlKey || event.metaKey;
        let target = command
            ? (key === 's'
                ? document.querySelector('[data-dusk="save-finding"]') ?? document.querySelector('[data-dusk="save-assessment"]')
                : key === 'enter' ? document.querySelector('[data-dusk="save-finding-next"]') : null)
            : (event.altKey && key === 'n' ? document.querySelector('[data-dusk="add-finding"]')
                : event.altKey && key === 't' ? document.querySelector('[data-dusk="add-template"]')
                    : key === 'escape' ? document.querySelector('[data-dusk="finding-close"]') : null);

        if (!target && !isInteractive(event.target) && document.querySelector('[data-assestme-finding-inspector]')) {
            target = key === 'arrowup'
                ? document.querySelector('[data-dusk="finding-previous"]')
                : key === 'arrowdown' ? document.querySelector('[data-dusk="finding-next"]') : null;
        }

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
    window.addEventListener('assestme-finding-selected', () => {
        requestAnimationFrame(() => {
            enhanceFindingRows();
            document.querySelector('[data-assestme-finding-inspector] input, [data-assestme-finding-inspector] textarea')?.focus();
        });
    });
    window.addEventListener('assestme-finding-validation-failed', () => {
        requestAnimationFrame(() => document.querySelector('[data-assestme-finding-inspector] [aria-invalid="true"]')?.focus());
    });
    registerLivewireHook();
})();
