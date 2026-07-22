(() => {
    'use strict';

    let dirty = false;
    let listScrollTop = 0;
    let livewireHookRegistered = false;
    let workspaceHeightFrame = null;
    let workspaceScrollRestoreFrame = null;
    const workspaceScrollSnapshots = new Map();

    const statusElement = () => document.querySelector('[data-assestme-save-status]');
    const listScroller = (list = document.querySelector('.assestme-findings-list')) => (
        list?.querySelector('.fi-ta-content-ctn') ?? list
    );

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

    const addPastedEvidence = (event) => {
        const images = Array.from(event.clipboardData?.files ?? [])
            .filter((file) => file.type.startsWith('image/'));
        if (images.length === 0) {
            document.documentElement.dataset.assestmeEvidencePaste = 'no-images';
            return;
        }

        const input = document.querySelector('.assestme-workbench-section--evidence input[type="file"]');
        const pond = input && !input.disabled
            ? input.closest('.fi-fo-file-upload')?._x_dataStack?.[0]?.pond
            : null;
        if (!pond) {
            document.documentElement.dataset.assestmeEvidencePaste = 'no-filepond';
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        document.documentElement.dataset.assestmeEvidencePaste = 'queued';
        Promise.all(images.map((file) => pond.addFile(file)))
            .then(() => {
                document.documentElement.dataset.assestmeEvidencePaste = 'added';
            })
            .catch(() => {
                document.documentElement.dataset.assestmeEvidencePaste = 'error';
            });
    };

    const revealExpandedPropertySection = (event) => {
        const header = event.target.closest?.('.assestme-workbench-properties .fi-section-header');
        if (!header) {
            return;
        }

        const section = header.closest('.fi-section');
        const panel = header.closest('.assestme-workbench-properties__body');
        window.setTimeout(() => {
            const content = section?.querySelector('.fi-section-content-ctn');
            if (!panel || content?.getAttribute('aria-expanded') !== 'true') {
                return;
            }

            const target = section.closest('.assestme-workbench-section') ?? section;
            const panelRect = panel.getBoundingClientRect();
            const targetRect = target.getBoundingClientRect();
            const bottomOverflow = targetRect.bottom - panelRect.bottom + 8;
            const topOverflow = targetRect.top - panelRect.top - 8;

            if (bottomOverflow > 0) {
                panel.scrollTo({ top: panel.scrollTop + bottomOverflow, behavior: 'smooth' });
            } else if (topOverflow < 0) {
                panel.scrollTo({ top: panel.scrollTop + topOverflow, behavior: 'smooth' });
            }
        }, 180);
    };

    const activateCompactSearch = (event) => {
        const field = event.target.closest?.('.assestme-findings-list .fi-ta-search-field');
        if (!field) {
            return;
        }

        field.querySelector('input[type="search"]')?.focus();
    };

    const updateWorkspaceAvailableHeight = () => {
        if (workspaceHeightFrame !== null) {
            cancelAnimationFrame(workspaceHeightFrame);
        }

        workspaceHeightFrame = requestAnimationFrame(() => {
            workspaceHeightFrame = null;

            document.querySelectorAll('[data-assestme-findings-workspace]').forEach((workspace) => {
                // Filament header and tabs can wrap, so use their real rendered offset instead of a fixed viewport subtraction.
                const workspaceTop = Math.max(0, workspace.getBoundingClientRect().top);
                const availableHeight = Math.max(320, Math.floor(window.innerHeight - workspaceTop - 16));

                workspace.style.setProperty('--assestme-workspace-available-height', `${availableHeight}px`);
            });
        });
    };

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

                listScrollTop = listScroller(row.closest('.assestme-findings-list'))?.scrollTop ?? 0;
                row.querySelector('.fi-ta-col')?.click();
            });
            row.addEventListener('keydown', (event) => {
                if (isInteractive(event.target) || !['Enter', ' '].includes(event.key)) {
                    return;
                }

                event.preventDefault();
                listScrollTop = listScroller(row.closest('.assestme-findings-list'))?.scrollTop ?? 0;
                row.querySelector('.fi-ta-col')?.click();
            });
        });
    };

    const refreshWorkspaceAfterMorph = () => {
        if (statusElement()?.dataset.status === 'saved') {
            dirty = false;
        }

        enhanceFindingRows();
        updateWorkspaceAvailableHeight();
    };

    const captureWorkspaceScroll = ({ component }) => {
        const componentElement = component?.el;
        const status = statusElement();
        const containsWorkspace = (status && componentElement?.contains?.(status))
            || componentElement?.hasAttribute?.('data-assestme-findings-workspace')
            || componentElement?.querySelector?.('[data-assestme-findings-workspace]');
        if (!containsWorkspace) {
            return;
        }

        cancelScheduledScrollRestore();
        const list = document.querySelector('.assestme-findings-list');
        const form = document.querySelector('.assestme-workbench-form');
        const editor = document.querySelector('[data-assestme-workbench-editor]');
        const properties = document.querySelector('.assestme-workbench-properties__body');
        const snapshot = {
            documentLeft: window.scrollX,
            documentTop: window.scrollY,
            editorTop: editor?.scrollTop ?? 0,
            formTop: form?.scrollTop ?? 0,
            listTop: listScroller(list)?.scrollTop ?? listScrollTop,
            propertiesTop: properties?.scrollTop ?? 0,
        };

        listScrollTop = snapshot.listTop;
        workspaceScrollSnapshots.set(component.id, snapshot);
    };

    const cancelScheduledScrollRestore = () => {
        if (workspaceScrollRestoreFrame === null) {
            return;
        }

        cancelAnimationFrame(workspaceScrollRestoreFrame);
        workspaceScrollRestoreFrame = null;
    };

    const restoreWorkspaceScroll = ({ component }) => {
        const snapshot = workspaceScrollSnapshots.get(component?.id);
        if (!snapshot) {
            return;
        }

        workspaceScrollSnapshots.delete(component.id);
        refreshWorkspaceAfterMorph();

        const restore = () => {
            const list = document.querySelector('.assestme-findings-list');
            const form = document.querySelector('.assestme-workbench-form');
            const editor = document.querySelector('[data-assestme-workbench-editor]');
            const properties = document.querySelector('.assestme-workbench-properties__body');

            if (list) {
                listScroller(list).scrollTop = snapshot.listTop;
            }

            if (form) {
                form.scrollTop = snapshot.formTop;
            }

            if (editor) {
                editor.scrollTop = snapshot.editorTop;
            }

            if (properties) {
                properties.scrollTop = snapshot.propertiesTop;
            }

            window.scrollTo(snapshot.documentLeft, snapshot.documentTop);
        };

        cancelScheduledScrollRestore();
        restore();
        workspaceScrollRestoreFrame = requestAnimationFrame(() => {
            workspaceScrollRestoreFrame = requestAnimationFrame(() => {
                workspaceScrollRestoreFrame = null;
                restore();
            });
        });
    };

    const registerLivewireHook = () => {
        if (livewireHookRegistered || !window.Livewire?.hook) {
            return;
        }

        livewireHookRegistered = true;
        window.Livewire.hook('morph', captureWorkspaceScroll);
        window.Livewire.hook('morphed', restoreWorkspaceScroll);
    };

    document.documentElement.dataset.assestmeWorkspaceAsset = 'loaded';
    enhanceFindingRows();
    updateWorkspaceAvailableHeight();

    document.addEventListener('input', (event) => {
        const element = workspaceStatusFor(event.target);

        if (!element) {
            return;
        }

        dirty = true;
        showStatus(navigator.onLine ? 'unsaved' : 'offline', element);
    });
    document.addEventListener('paste', addPastedEvidence);
    document.addEventListener('click', activateCompactSearch);
    document.addEventListener('click', revealExpandedPropertySection);

    document.addEventListener('scroll', (event) => {
        if (event.target.matches?.('.assestme-findings-list, .assestme-findings-list .fi-ta-content-ctn')) {
            listScrollTop = event.target.scrollTop;
        }
    }, true);

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
    window.addEventListener('resize', updateWorkspaceAvailableHeight);
    window.addEventListener('assestme-finding-selected', () => {
        cancelScheduledScrollRestore();
        requestAnimationFrame(() => {
            enhanceFindingRows();
            updateWorkspaceAvailableHeight();
            const editor = document.querySelector('[data-assestme-workbench-editor]');
            const properties = document.querySelector('.assestme-workbench-properties__body');
            if (editor) {
                editor.scrollTop = 0;
            }
            if (properties) {
                properties.scrollTop = 0;
            }
            editor?.querySelector('input, textarea')?.focus();
        });
    });
    window.addEventListener('assestme-finding-validation-failed', () => {
        cancelScheduledScrollRestore();
        requestAnimationFrame(() => document.querySelector('.assestme-workbench-form [aria-invalid="true"]')?.focus());
    });
    registerLivewireHook();
})();
