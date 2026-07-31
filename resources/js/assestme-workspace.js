(() => {
    'use strict';

    let listScrollTop = 0;
    let livewireHookRegistered = false;
    let workspaceHeightFrame = null;
    let workspaceScrollRestoreFrame = null;
    const workspaceEntityStates = new Map();
    const workspaceScrollSnapshots = new Map();

    const activeEntityKey = () => {
        const context = document.querySelector('[data-assestme-workspace-context]');
        const form = context?.querySelector('[data-assestme-draft-form]');
        const kind = form?.dataset.assestmeDraftForm ?? context?.dataset.activeForm;
        const userId = context?.dataset.userId;
        const assessmentId = context?.dataset.assessmentId;

        if (!userId || !assessmentId) {
            return null;
        }

        if (kind === 'assessment') {
            return `user:${userId}:assessment:${assessmentId}:metadata`;
        }

        const findingId = context?.dataset.findingId;

        return kind === 'finding' && findingId
            ? `user:${userId}:assessment:${assessmentId}:finding:${findingId}`
            : null;
    };

    const stateForEntity = (entityKey, create = true) => {
        if (!entityKey) {
            return null;
        }

        if (!workspaceEntityStates.has(entityKey) && create) {
            workspaceEntityStates.set(entityKey, {
                dirty: false,
                localDraftIsDurable: false,
                localDraftStatus: 'local',
                excludedEvidenceIsDirty: false,
                statusBeforeOffline: null,
            });
        }

        return workspaceEntityStates.get(entityKey) ?? null;
    };

    const stateFromDraftEvent = (event) => {
        const entityKey = event.detail?.entityKey;

        if (typeof entityKey !== 'string' || entityKey.length === 0) {
            return null;
        }

        return {
            entityKey,
            state: stateForEntity(entityKey),
        };
    };

    const statusElement = () => document.querySelector('[data-assestme-save-status]');
    const listScroller = (list = document.querySelector('.assestme-findings-list')) => (
        list?.querySelector('.fi-ta-content-ctn') ?? list
    );

    const showStatus = (status, element = statusElement()) => {
        if (!element) {
            return;
        }

        const labelKey = `${status.replace(/_([a-z])/g, (_, letter) => letter.toUpperCase())}Label`;
        const label = element.dataset[labelKey];

        if (label) {
            element.textContent = label;
            element.dataset.status = status;
        }
    };

    const currentStatusForState = (state) => {
        if (!navigator.onLine) {
            return 'offline';
        }

        if (state.excludedEvidenceIsDirty) {
            return 'unsaved';
        }

        if (state.localDraftIsDurable) {
            return state.localDraftStatus;
        }

        return state.localDraftStatus === 'storage_error' ? 'storage_error' : 'unsaved';
    };

    const showStatusForEntity = (entityKey, state) => {
        if (entityKey === activeEntityKey()) {
            showStatus(currentStatusForState(state));
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
        const entityKey = activeEntityKey();
        const state = stateForEntity(entityKey);
        if (state) {
            state.dirty = true;
            state.localDraftIsDurable = false;
            state.localDraftStatus = 'local';
            state.excludedEvidenceIsDirty = true;
            showStatusForEntity(entityKey, state);
            window.dispatchEvent(new CustomEvent('assestme-evidence-dirty', {
                detail: { entityKey },
            }));
        }
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
        const renderedStatus = statusElement()?.dataset.status;
        const state = stateForEntity(activeEntityKey(), false);
        if (state?.dirty && ['saved', 'unsaved'].includes(renderedStatus)) {
            showStatus(currentStatusForState(state));
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

    const markWorkspaceDirty = (event) => {
        const element = workspaceStatusFor(event.target);

        if (!element) {
            return;
        }

        const entityKey = activeEntityKey();
        const state = stateForEntity(entityKey);
        if (!state) {
            return;
        }

        state.dirty = true;
        state.localDraftIsDurable = false;
        state.localDraftStatus = 'local';
        if (event.target.closest?.('.assestme-workbench-section--evidence') || event.target.matches?.('input[type="file"]')) {
            state.excludedEvidenceIsDirty = true;
        }
        showStatus(currentStatusForState(state), element);
    };

    document.addEventListener('input', markWorkspaceDirty);
    document.addEventListener('change', markWorkspaceDirty);
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
        const entityKey = activeEntityKey();
        const state = stateForEntity(entityKey);
        const element = statusElement();
        if (!element || !state) {
            return;
        }

        if (element.dataset.status !== 'offline') {
            state.statusBeforeOffline = element.dataset.status ?? null;
        }
        showStatus('offline');
    });

    window.addEventListener('online', () => {
        const state = stateForEntity(activeEntityKey(), false);
        if (state?.dirty) {
            showStatus(currentStatusForState(state));
        } else if (state?.statusBeforeOffline) {
            showStatus(state.statusBeforeOffline);
        }
        if (state) {
            state.statusBeforeOffline = null;
        }
    });

    window.addEventListener('beforeunload', (event) => {
        const hasUnprotectedChanges = Array.from(workspaceEntityStates.values()).some((state) => (
            state.dirty && (!state.localDraftIsDurable || state.excludedEvidenceIsDirty)
        ));

        if (!hasUnprotectedChanges) {
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
            refreshWorkspaceAfterMorph();
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
    window.addEventListener('assestme-local-draft-persisted', (event) => {
        const draftEvent = stateFromDraftEvent(event);
        if (!draftEvent) {
            return;
        }

        if (event.detail?.clearEvidence === true) {
            draftEvent.state.excludedEvidenceIsDirty = false;
        }
        draftEvent.state.dirty = true;
        draftEvent.state.localDraftIsDurable = true;
        draftEvent.state.localDraftStatus = event.detail?.stale ? 'stale' : 'local';
        showStatusForEntity(draftEvent.entityKey, draftEvent.state);
    });
    window.addEventListener('assestme-local-draft-dirty', (event) => {
        const draftEvent = stateFromDraftEvent(event);
        if (!draftEvent) {
            return;
        }

        if (event.detail?.clearEvidence === true) {
            draftEvent.state.excludedEvidenceIsDirty = false;
        }
        draftEvent.state.dirty = true;
        draftEvent.state.localDraftIsDurable = false;
        draftEvent.state.localDraftStatus = 'local';
        showStatusForEntity(draftEvent.entityKey, draftEvent.state);
    });
    window.addEventListener('assestme-local-draft-restored', (event) => {
        const draftEvent = stateFromDraftEvent(event);
        if (!draftEvent) {
            return;
        }

        draftEvent.state.dirty = true;
        draftEvent.state.localDraftIsDurable = true;
        draftEvent.state.localDraftStatus = event.detail?.stale ? 'stale' : 'local';
        showStatusForEntity(draftEvent.entityKey, draftEvent.state);
    });
    window.addEventListener('assestme-local-draft-failed', (event) => {
        const draftEvent = stateFromDraftEvent(event);
        if (!draftEvent) {
            return;
        }

        draftEvent.state.dirty = true;
        draftEvent.state.localDraftIsDurable = false;
        draftEvent.state.localDraftStatus = 'storage_error';
        showStatusForEntity(draftEvent.entityKey, draftEvent.state);
    });
    window.addEventListener('assestme-local-draft-clean', (event) => {
        const draftEvent = stateFromDraftEvent(event);
        if (!draftEvent) {
            return;
        }

        if (event.detail?.clearEvidence !== true && draftEvent.state.excludedEvidenceIsDirty) {
            draftEvent.state.dirty = true;
            draftEvent.state.localDraftIsDurable = false;
            draftEvent.state.localDraftStatus = 'local';
            showStatusForEntity(draftEvent.entityKey, draftEvent.state);

            return;
        }

        draftEvent.state.dirty = false;
        draftEvent.state.localDraftIsDurable = false;
        draftEvent.state.localDraftStatus = 'local';
        draftEvent.state.excludedEvidenceIsDirty = false;
        if (draftEvent.entityKey === activeEntityKey()) {
            showStatus('saved');
        }
    });
    registerLivewireHook();
})();
