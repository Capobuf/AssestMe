(() => {
    'use strict';

    let dirty = false;

    const statusElement = () => document.querySelector('[data-assestme-save-status]');

    const showStatus = (status) => {
        const element = statusElement();

        if (!element) {
            return;
        }

        const label = element.dataset[`${status}Label`];

        if (label) {
            element.textContent = label;
            element.dataset.status = status;
        }
    };

    document.documentElement.dataset.assestmeWorkspaceAsset = 'loaded';

    document.addEventListener('input', (event) => {
        if (!event.target.closest('[wire\\:id]')) {
            return;
        }

        dirty = true;
        showStatus(navigator.onLine ? 'unsaved' : 'offline');
    });

    window.addEventListener('offline', () => {
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

    document.addEventListener('livewire:init', () => {
        window.Livewire.hook('morph.updated', () => {
            if (statusElement()?.dataset.status === 'saved') {
                dirty = false;
            }
        });
    });
})();
