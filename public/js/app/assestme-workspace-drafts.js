(() => {
    'use strict';

    const DATABASE_NAME = 'assestme-workspace';
    const DATABASE_VERSION = 1;
    const DRAFT_STORE = 'drafts';
    const DRAFT_SCHEMA_VERSION = 1;
    const DRAFT_DEBOUNCE_MS = 500;

    const FINDING_DRAFT_FIELDS = [
        'title',
        'problem',
        'entrepreneur_notes',
        'technical_notes',
        'category_id',
        'scope_type',
        'scope_description',
        'site_ids',
        'asset_ids',
        'consequence_level_id',
        'likelihood_level_id',
        'priority_level_id',
        'priority_is_overridden',
        'priority_rationale',
        'status',
        'include_in_report',
        'resolution_notes',
        'solutions',
    ];

    const ASSESSMENT_DRAFT_FIELDS = [
        'title',
        'assessment_date',
        'report_title_override',
        'scope_type',
        'scope_description',
        'introduction',
        'executive_summary',
        'methodology_notes',
        'site_ids',
    ];

    const activeDraftIds = new Map();
    const debounceTimers = new Map();
    const draftMutationQueues = new Map();
    const editRevisions = new Map();
    const evidenceRevisions = new Map();
    const pendingAttempts = new Map();
    const newerPayloads = new Map();
    const structuralDraftChanges = new Map();
    let databasePromise = null;
    let displayedDraft = null;
    let livewireRegistered = false;
    let persistentStorageRequested = false;
    let persistentStorageGranted = null;
    let recoveryGeneration = 0;
    let recoveryFrame = null;
    let saveInProgress = false;
    let storageUnavailable = false;
    let lastStorageError = null;

    const createUuid = () => {
        const browserCrypto = window.crypto;

        if (typeof browserCrypto?.randomUUID === 'function') {
            return browserCrypto.randomUUID();
        }

        if (typeof browserCrypto?.getRandomValues !== 'function') {
            throw new DOMException('Secure browser randomness is unavailable.', 'SecurityError');
        }

        const bytes = browserCrypto.getRandomValues(new Uint8Array(16));
        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;

        const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0'));

        return [
            hex.slice(0, 4).join(''),
            hex.slice(4, 6).join(''),
            hex.slice(6, 8).join(''),
            hex.slice(8, 10).join(''),
            hex.slice(10, 16).join(''),
        ].join('-');
    };

    const containsBinaryValue = (value, seen = new WeakSet()) => {
        if (typeof File !== 'undefined' && value instanceof File) {
            return true;
        }
        if (typeof Blob !== 'undefined' && value instanceof Blob) {
            return true;
        }
        if (value === null || typeof value !== 'object') {
            return false;
        }
        if (seen.has(value)) {
            return true;
        }

        seen.add(value);

        return Object.values(value).some((item) => containsBinaryValue(item, seen));
    };

    const toPlainObject = (value) => {
        if (containsBinaryValue(value)) {
            throw new DOMException('Binary values cannot be stored in a Workspace draft.', 'DataCloneError');
        }

        const json = JSON.stringify(value);

        return json === undefined ? null : JSON.parse(json);
    };

    const fieldsForKind = (kind) => kind === 'finding'
        ? FINDING_DRAFT_FIELDS
        : ASSESSMENT_DRAFT_FIELDS;

    const pickFields = (state, fields) => Object.fromEntries(
        fields
            .filter((field) => Object.hasOwn(state, field))
            .map((field) => [field, toPlainObject(state[field])]),
    );

    const draftPayload = (kind, state) => {
        const payload = pickFields(state, fieldsForKind(kind));
        if (kind === 'finding' && Object.hasOwn(payload, 'solutions')) {
            payload.solutions = Object.values(payload.solutions ?? {});
        }

        return payload;
    };

    const normalizeJsonValue = (value) => {
        if (Array.isArray(value)) {
            return value.map(normalizeJsonValue);
        }
        if (value === null || typeof value !== 'object') {
            return value;
        }

        return Object.fromEntries(
            Object.keys(value)
                .sort()
                .map((key) => [key, normalizeJsonValue(value[key])]),
        );
    };

    const normalizePayload = (kind, payload) => JSON.stringify(
        normalizeJsonValue(draftPayload(kind, payload)),
    );

    const parsePositiveInteger = (value) => {
        const parsed = Number.parseInt(value ?? '', 10);

        return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
    };

    const contextKey = (context) => `${context.entityKey}:${context.tabId}`;

    const revisionFor = (key) => editRevisions.get(key) ?? 0;

    const evidenceRevisionFor = (key) => evidenceRevisions.get(key) ?? 0;

    const incrementRevision = (key) => {
        const revision = revisionFor(key) + 1;
        editRevisions.set(key, revision);

        return revision;
    };

    const queueDraftMutation = (key, callback) => {
        const previous = draftMutationQueues.get(key) ?? Promise.resolve();
        const current = previous.catch(() => undefined).then(callback);
        let tail;
        tail = current.catch(() => undefined).finally(() => {
            if (draftMutationQueues.get(key) === tail) {
                draftMutationQueues.delete(key);
            }
        });
        draftMutationQueues.set(key, tail);

        return current;
    };

    const workspaceContext = () => document.querySelector('[data-assestme-workspace-context]');

    const resolveContext = () => {
        const element = workspaceContext();
        if (!element) {
            return null;
        }

        const form = element.querySelector('[data-assestme-draft-form]');
        const kind = form?.dataset.assestmeDraftForm;
        const statePath = form?.dataset.assestmeDraftStatePath;
        const userId = parsePositiveInteger(element.dataset.userId);
        const assessmentId = parsePositiveInteger(element.dataset.assessmentId);
        const findingId = kind === 'finding' ? parsePositiveInteger(element.dataset.findingId) : null;
        const expectedVersion = Number.parseInt(element.dataset.expectedVersion ?? '', 10);
        const componentElement = element.closest('[wire\\:id]');
        const componentId = componentElement?.getAttribute('wire:id');
        const wire = componentId ? window.Livewire?.find(componentId) : null;

        if (!['finding', 'assessment'].includes(kind)
            || !statePath
            || userId === null
            || assessmentId === null
            || (kind === 'finding' && findingId === null)
            || !Number.isInteger(expectedVersion)
            || !element.dataset.tabId
            || !wire) {
            return null;
        }

        const entityKey = kind === 'finding'
            ? `user:${userId}:assessment:${assessmentId}:finding:${findingId}`
            : `user:${userId}:assessment:${assessmentId}:metadata`;

        return {
            assessmentId,
            element,
            entityKey,
            expectedVersion,
            findingId,
            form,
            kind,
            readOnly: element.dataset.readOnly === 'true',
            statePath,
            tabId: element.dataset.tabId,
            userId,
            wire,
        };
    };

    const currentPayload = (context) => {
        const state = context.wire.$get(context.statePath);
        if (state === null || typeof state !== 'object') {
            throw new DOMException('The Livewire form state is unavailable.', 'InvalidStateError');
        }

        return draftPayload(context.kind, state);
    };

    const openDatabase = () => {
        if (storageUnavailable) {
            return Promise.reject(lastStorageError);
        }
        if (databasePromise) {
            return databasePromise;
        }
        if (!window.indexedDB) {
            lastStorageError = new DOMException('IndexedDB is unavailable.', 'InvalidStateError');
            storageUnavailable = true;

            return Promise.reject(lastStorageError);
        }

        databasePromise = new Promise((resolve, reject) => {
            const request = window.indexedDB.open(DATABASE_NAME, DATABASE_VERSION);
            let settled = false;

            request.onupgradeneeded = () => {
                const database = request.result;
                const store = database.objectStoreNames.contains(DRAFT_STORE)
                    ? request.transaction.objectStore(DRAFT_STORE)
                    : database.createObjectStore(DRAFT_STORE, { keyPath: 'id' });

                ['entityKey', 'requestId', 'attemptedRequestId', 'updatedAt'].forEach((indexName) => {
                    if (!store.indexNames.contains(indexName)) {
                        store.createIndex(indexName, indexName, { unique: false });
                    }
                });
            };
            request.onerror = () => {
                settled = true;
                reject(request.error ?? new DOMException('IndexedDB could not be opened.', 'InvalidStateError'));
            };
            request.onblocked = () => {
                settled = true;
                reject(new DOMException('IndexedDB opening was blocked.', 'InvalidStateError'));
            };
            request.onsuccess = () => {
                if (settled) {
                    request.result.close();
                    return;
                }

                const database = request.result;
                database.onversionchange = () => database.close();
                resolve(database);
            };
        }).catch((error) => {
            databasePromise = null;
            storageUnavailable = true;
            lastStorageError = error;
            throw error;
        });

        return databasePromise;
    };

    const executeRequest = async (mode, createRequest) => {
        const database = await openDatabase();

        return new Promise((resolve, reject) => {
            let request;
            let result;
            let transaction;

            try {
                transaction = database.transaction(DRAFT_STORE, mode);
                request = createRequest(transaction.objectStore(DRAFT_STORE));
            } catch (error) {
                reject(error);
                return;
            }

            request.onsuccess = () => {
                result = request.result;
            };
            transaction.oncomplete = () => resolve(result);
            transaction.onerror = () => reject(
                transaction.error ?? request.error ?? new DOMException('IndexedDB transaction failed.', 'InvalidStateError'),
            );
            transaction.onabort = () => reject(
                transaction.error ?? request.error ?? new DOMException('IndexedDB transaction was aborted.', 'InvalidStateError'),
            );
        });
    };

    const draftsForEntity = async (entityKey) => {
        const drafts = await executeRequest(
            'readonly',
            (store) => store.index('entityKey').getAll(window.IDBKeyRange.only(entityKey)),
        );

        return (drafts ?? []).sort((left, right) => right.updatedAt.localeCompare(left.updatedAt));
    };

    const draftsForIndex = async (indexName, value) => executeRequest(
        'readonly',
        (store) => store.index(indexName).getAll(window.IDBKeyRange.only(value)),
    );

    const findDraft = async (id) => executeRequest('readonly', (store) => store.get(id));
    const putDraft = async (draft) => executeRequest('readwrite', (store) => store.put(draft));
    const deleteDraft = async (id) => executeRequest('readwrite', (store) => store.delete(id));

    const deleteDraftIf = async (id, predicate) => {
        const database = await openDatabase();

        return new Promise((resolve, reject) => {
            let deleted = false;
            let transaction;

            try {
                transaction = database.transaction(DRAFT_STORE, 'readwrite');
                const store = transaction.objectStore(DRAFT_STORE);
                const request = store.get(id);

                request.onsuccess = () => {
                    if (!request.result || !predicate(request.result)) {
                        return;
                    }

                    deleted = true;
                    store.delete(id);
                };
            } catch (error) {
                reject(error);
                return;
            }

            transaction.oncomplete = () => resolve(deleted);
            transaction.onerror = () => reject(
                transaction.error ?? new DOMException('IndexedDB transaction failed.', 'InvalidStateError'),
            );
            transaction.onabort = () => reject(
                transaction.error ?? new DOMException('IndexedDB transaction was aborted.', 'InvalidStateError'),
            );
        });
    };

    const updateDraftIf = async (id, predicate, update) => {
        const database = await openDatabase();

        return new Promise((resolve, reject) => {
            let transaction;
            let updated = false;

            try {
                transaction = database.transaction(DRAFT_STORE, 'readwrite');
                const store = transaction.objectStore(DRAFT_STORE);
                const request = store.get(id);

                request.onsuccess = () => {
                    if (!request.result || !predicate(request.result)) {
                        return;
                    }

                    updated = true;
                    store.put(update(request.result));
                };
            } catch (error) {
                reject(error);
                return;
            }

            transaction.oncomplete = () => resolve(updated);
            transaction.onerror = () => reject(
                transaction.error ?? new DOMException('IndexedDB transaction failed.', 'InvalidStateError'),
            );
            transaction.onabort = () => reject(
                transaction.error ?? new DOMException('IndexedDB transaction was aborted.', 'InvalidStateError'),
            );
        });
    };

    const emit = (name, detail = {}) => window.dispatchEvent(new CustomEvent(name, { detail }));

    const saveStatusElement = () => workspaceContext()?.querySelector('[data-assestme-save-status]');

    const showSaveStatus = (status, entityKey = null) => {
        if (entityKey !== null && resolveContext()?.entityKey !== entityKey) {
            return;
        }

        const element = saveStatusElement();
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

    const banner = () => workspaceContext()?.querySelector('[data-assestme-local-draft]');

    const hideBanner = () => {
        const element = banner();
        if (element) {
            element.hidden = true;
        }
        displayedDraft = null;
    };

    const replacement = (template, count) => template.replaceAll(':count', String(count));

    const formattedTimestamp = (timestamp) => {
        const date = new Date(timestamp);
        if (Number.isNaN(date.getTime())) {
            return timestamp;
        }

        try {
            return new Intl.DateTimeFormat(document.documentElement.lang || undefined, {
                dateStyle: 'medium',
                timeStyle: 'short',
            }).format(date);
        } catch {
            return date.toLocaleString();
        }
    };

    const showDraft = (context, draft, count) => {
        const element = banner();
        if (!element) {
            return;
        }

        const stale = draft.baseVersion !== context.expectedVersion;
        const readOnly = context.readOnly;
        const incompatible = draft.schemaVersion !== DRAFT_SCHEMA_VERSION;
        const title = element.querySelector('[data-assestme-local-draft-title]');
        const updatedAt = element.querySelector('[data-assestme-local-draft-updated-at]');
        const version = element.querySelector('[data-assestme-local-draft-version]');
        const countElement = element.querySelector('[data-assestme-local-draft-count]');
        const error = element.querySelector('[data-assestme-local-draft-error]');
        const actions = element.querySelector('[data-assestme-local-draft-actions]');
        const restore = element.querySelector('[data-dusk="restore-local-draft"]');
        const persistenceNote = element.querySelector('[data-assestme-storage-persistence-note]');

        element.dataset.draftStatus = incompatible
            ? 'error'
            : (readOnly ? 'read-only' : (stale ? 'stale' : 'current'));
        element.hidden = false;
        if (title) {
            title.textContent = incompatible
                ? element.dataset.schemaIncompatibleLabel
                : (readOnly ? element.dataset.readOnlyTitleLabel : element.dataset.availableTitleLabel);
        }
        if (updatedAt) {
            updatedAt.textContent = formattedTimestamp(draft.updatedAt);
            updatedAt.setAttribute('datetime', draft.updatedAt);
        }
        if (version) {
            version.textContent = incompatible
                ? element.dataset.schemaIncompatibleLabel
                : (stale ? element.dataset.staleVersionLabel : element.dataset.currentVersionLabel);
        }
        if (countElement) {
            const template = count === 1
                ? element.dataset.countOneLabel
                : element.dataset.countManyLabel;
            countElement.textContent = replacement(template, count);
        }
        if (error) {
            error.textContent = element.dataset.storageUnavailableLabel;
            error.hidden = true;
        }
        if (actions) {
            actions.hidden = false;
        }
        if (restore) {
            restore.disabled = readOnly || draft.schemaVersion !== DRAFT_SCHEMA_VERSION;
            restore.setAttribute('aria-disabled', restore.disabled ? 'true' : 'false');
        }
        if (persistenceNote) {
            persistenceNote.hidden = persistentStorageGranted !== false;
        }

        displayedDraft = {
            contextKey: `${context.entityKey}:${context.tabId}`,
            draft,
            restored: false,
            stale,
        };
        showSaveStatus(incompatible || stale ? 'stale' : 'local');
    };

    const updatePersistentStorageNote = () => {
        const persistenceNote = banner()?.querySelector('[data-assestme-storage-persistence-note]');
        if (persistenceNote) {
            persistenceNote.hidden = persistentStorageGranted !== false;
        }
    };

    const showStorageFailure = (error) => {
        const element = banner();
        storageUnavailable = true;
        lastStorageError = error;
        document.documentElement.dataset.assestmeWorkspaceDraftStorage = 'error';
        showSaveStatus('storage_error');
        emit('assestme-local-draft-failed', {
            entityKey: resolveContext()?.entityKey ?? null,
            errorName: error?.name ?? 'Error',
        });

        if (!element) {
            return;
        }

        const title = element.querySelector('[data-assestme-local-draft-title]');
        const errorMessage = element.querySelector('[data-assestme-local-draft-error]');
        const actions = element.querySelector('[data-assestme-local-draft-actions]');
        const version = element.querySelector('[data-assestme-local-draft-version]');
        const countElement = element.querySelector('[data-assestme-local-draft-count]');

        element.dataset.draftStatus = 'error';
        element.dataset.storageErrorName = error?.name ?? 'Error';
        element.hidden = false;
        displayedDraft = null;
        if (title) {
            title.textContent = element.dataset.storageErrorTitleLabel;
        }
        if (errorMessage) {
            errorMessage.textContent = element.dataset.storageUnavailableLabel;
            errorMessage.hidden = false;
        }
        if (actions) {
            actions.hidden = true;
        }
        if (version) {
            version.textContent = element.dataset.storageUnavailableLabel;
        }
        if (countElement) {
            countElement.textContent = '';
        }
    };

    const requestPersistentStorage = async () => {
        if (persistentStorageRequested) {
            return;
        }

        persistentStorageRequested = true;
        if (typeof navigator.storage?.persisted !== 'function'
            || typeof navigator.storage?.persist !== 'function') {
            return;
        }

        try {
            const alreadyPersistent = await navigator.storage.persisted();
            persistentStorageGranted = alreadyPersistent || await navigator.storage.persist();
            document.documentElement.dataset.assestmeWorkspacePersistentStorage = persistentStorageGranted
                ? 'granted'
                : 'not-granted';
            updatePersistentStorageNote();
        } catch {
            persistentStorageGranted = false;
            document.documentElement.dataset.assestmeWorkspacePersistentStorage = 'not-granted';
            updatePersistentStorageNote();
        }
    };

    const samePayload = (kind, left, right) => normalizePayload(kind, left) === normalizePayload(kind, right);

    const activeDraftFor = async (context) => {
        const knownId = activeDraftIds.get(context.entityKey);
        if (knownId) {
            const known = await findDraft(knownId);
            if (known?.tabId === context.tabId && known.schemaVersion === DRAFT_SCHEMA_VERSION) {
                return known;
            }
            activeDraftIds.delete(context.entityKey);
        }

        const drafts = await draftsForEntity(context.entityKey);
        const current = drafts.find((draft) => (
            draft.tabId === context.tabId && draft.schemaVersion === DRAFT_SCHEMA_VERSION
        ));
        if (current) {
            activeDraftIds.set(context.entityKey, current.id);
        }

        return current ?? null;
    };

    const persistCurrentDraftUnlocked = async (context, suppliedPayload = null) => {
        if (context.readOnly) {
            throw new DOMException('Read-only assessments cannot create local drafts.', 'InvalidStateError');
        }

        const payload = suppliedPayload ?? currentPayload(context);
        let draft = await activeDraftFor(context);
        const timestamp = new Date().toISOString();

        if (!draft) {
            draft = {
                id: createUuid(),
                schemaVersion: DRAFT_SCHEMA_VERSION,
                entityKey: context.entityKey,
                kind: context.kind,
                userId: context.userId,
                assessmentId: context.assessmentId,
                findingId: context.findingId,
                tabId: context.tabId,
                baseVersion: context.expectedVersion,
                requestId: createUuid(),
                attemptedRequestId: null,
                attemptedAt: null,
                payload,
                updatedAt: timestamp,
            };
        } else {
            const changed = !samePayload(context.kind, draft.payload, payload);
            if (changed
                && draft.attemptedRequestId !== null
                && draft.requestId === draft.attemptedRequestId) {
                draft.requestId = createUuid();
            }
            draft = {
                ...draft,
                kind: context.kind,
                userId: context.userId,
                assessmentId: context.assessmentId,
                findingId: context.findingId,
                tabId: context.tabId,
                payload,
                updatedAt: timestamp,
                ...(changed ? {
                    confirmedAppliedVersion: null,
                    confirmedByRequestId: null,
                } : {}),
            };
        }

        await putDraft(draft);
        activeDraftIds.set(context.entityKey, draft.id);
        document.documentElement.dataset.assestmeWorkspaceDraft = 'persisted';
        emit('assestme-local-draft-persisted', {
            entityKey: context.entityKey,
            stale: draft.baseVersion !== context.expectedVersion,
        });

        return draft;
    };

    const persistCurrentDraft = (context, suppliedPayload = null) => queueDraftMutation(
        contextKey(context),
        () => persistCurrentDraftUnlocked(context, suppliedPayload),
    );

    const isEvidenceTarget = (target) => Boolean(
        target.closest?.('.assestme-workbench-section--evidence')
        || target.matches?.('input[type="file"]'),
    );

    const scheduleDraftWrite = (event) => {
        const context = resolveContext();
        if (!context || context.readOnly || !context.form.contains(event.target)) {
            return;
        }

        const key = contextKey(context);
        if (isEvidenceTarget(event.target)) {
            evidenceRevisions.set(key, evidenceRevisionFor(key) + 1);
            return;
        }

        let payload;
        try {
            payload = currentPayload(context);
        } catch (error) {
            showStorageFailure(error);
            return;
        }

        incrementRevision(key);

        if (saveInProgress) {
            newerPayloads.set(key, payload);
        }

        void requestPersistentStorage();
        const previousTimer = debounceTimers.get(key);
        if (previousTimer !== undefined) {
            window.clearTimeout(previousTimer);
        }

        const timer = window.setTimeout(async () => {
            if (debounceTimers.get(key) === timer) {
                debounceTimers.delete(key);
            }

            try {
                await persistCurrentDraft(context, payload);
            } catch (error) {
                showStorageFailure(error);
            }
        }, DRAFT_DEBOUNCE_MS);
        debounceTimers.set(key, timer);
    };

    const rememberPastedEvidence = (event) => {
        const context = resolveContext();
        if (!context || context.readOnly || event.detail?.entityKey !== context.entityKey) {
            return;
        }

        const key = contextKey(context);
        evidenceRevisions.set(key, evidenceRevisionFor(key) + 1);
    };

    const markAttempt = (context, draft) => queueDraftMutation(contextKey(context), async () => {
        const stored = await findDraft(draft.id);
        const current = stored?.tabId === context.tabId ? stored : draft;
        const attemptedAt = new Date().toISOString();
        const attempted = {
            ...current,
            attemptedRequestId: current.requestId,
            attemptedAt,
        };

        await putDraft(attempted);
        pendingAttempts.set(attempted.requestId, {
            draftId: attempted.id,
            entityKey: context.entityKey,
            evidenceRevision: evidenceRevisionFor(contextKey(context)),
            payload: attempted.payload,
            revision: revisionFor(contextKey(context)),
            tabId: context.tabId,
        });

        return attempted;
    });

    const saveThroughLivewire = async (method) => {
        const context = resolveContext();
        if (!context || context.readOnly || saveInProgress) {
            return;
        }

        saveInProgress = true;
        const key = contextKey(context);
        const scheduledTimer = debounceTimers.get(key);
        if (scheduledTimer !== undefined) {
            window.clearTimeout(scheduledTimer);
            debounceTimers.delete(key);
        }
        void requestPersistentStorage();

        let draft = null;
        let requestId = null;
        try {
            let payload = currentPayload(context);
            try {
                draft = await persistCurrentDraft(context, payload);
                requestId = draft.requestId;
            } catch (error) {
                showStorageFailure(error);
                requestId = createUuid();
            }

            if (!navigator.onLine) {
                showSaveStatus(draft ? 'offline' : 'storage_error', context.entityKey);
                return;
            }

            const pendingPath = context.kind === 'finding'
                ? 'pendingFindingRequestId'
                : 'pendingAssessmentRequestId';
            if (draft) {
                let prepared = false;

                for (let preparation = 0; preparation < 3; preparation += 1) {
                    try {
                        draft = await markAttempt(context, draft);
                        requestId = draft.requestId;
                        await context.wire.$set(pendingPath, requestId, false);
                    } catch (error) {
                        showStorageFailure(error);
                        requestId = draft.requestId;
                        await context.wire.$set(pendingPath, requestId, false);
                        pendingAttempts.set(requestId, {
                            draftId: draft.id,
                            entityKey: context.entityKey,
                            evidenceRevision: evidenceRevisionFor(key),
                            payload: draft.payload,
                            revision: revisionFor(key),
                            tabId: context.tabId,
                        });
                    }

                    const latestPayload = currentPayload(context);
                    if (samePayload(context.kind, payload, latestPayload)) {
                        prepared = true;
                        break;
                    }

                    pendingAttempts.delete(requestId);
                    payload = latestPayload;
                    draft = await persistCurrentDraft(context, payload);
                }

                if (!prepared) {
                    draft = {
                        ...draft,
                        attemptedRequestId: null,
                        attemptedAt: null,
                    };
                    await queueDraftMutation(key, () => putDraft(draft));
                    await context.wire.$set(pendingPath, null, false);
                    showSaveStatus('local', context.entityKey);
                    return;
                }
            } else {
                await context.wire.$set(pendingPath, requestId, false);
                pendingAttempts.set(requestId, {
                    draftId: null,
                    entityKey: context.entityKey,
                    evidenceRevision: evidenceRevisionFor(key),
                    payload,
                    revision: revisionFor(key),
                    tabId: context.tabId,
                });
            }

            await context.wire.$call(method);
        } catch {
            if (draft) {
                showSaveStatus(navigator.onLine ? 'local' : 'offline', context.entityKey);
            } else {
                showSaveStatus(navigator.onLine ? 'storage_error' : 'offline', context.entityKey);
            }
        } finally {
            saveInProgress = false;
        }
    };

    const interceptSubmit = (event) => {
        const form = event.target.closest?.('[data-assestme-draft-form]');
        if (!form) {
            return;
        }

        const context = resolveContext();
        if (!context || context.form !== form || context.readOnly) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        if (saveInProgress) {
            return;
        }

        const method = form.dataset.assestmeDraftForm === 'finding'
            ? 'saveFinding'
            : 'saveAssessmentDetails';
        void saveThroughLivewire(method);
    };

    const interceptSaveAndNext = (event) => {
        const button = event.target.closest?.('[data-dusk="save-finding-next"]');
        if (!button) {
            return;
        }

        const context = resolveContext();
        if (!context || context.kind !== 'finding' || context.readOnly) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        if (!saveInProgress) {
            void saveThroughLivewire('saveFindingAndNext');
        }
    };

    const evaluateRecovery = async () => {
        const generation = ++recoveryGeneration;
        const context = resolveContext();
        if (!context) {
            hideBanner();
            document.documentElement.dataset.assestmeWorkspaceDraftRecovery = 'complete';
            return;
        }

        try {
            const serverPayload = currentPayload(context);
            const drafts = await draftsForEntity(context.entityKey);
            const pristine = revisionFor(contextKey(context)) === 0;
            if (generation !== recoveryGeneration) {
                return;
            }

            const remaining = [];
            for (const draft of drafts) {
                if (draft.confirmedByRequestId
                    && Number.isInteger(draft.confirmedAppliedVersion)
                    && draft.confirmedAppliedVersion <= context.expectedVersion) {
                    continue;
                }
                if (draft.schemaVersion !== DRAFT_SCHEMA_VERSION) {
                    remaining.push(draft);
                    continue;
                }

                const exactMatch = samePayload(context.kind, draft.payload, serverPayload);
                if (!context.readOnly && pristine && draft.tabId !== context.tabId && exactMatch) {
                    const deleted = await queueDraftMutation(
                        `${draft.entityKey}:${draft.tabId}`,
                        () => deleteDraftIf(draft.id, (stored) => (
                            stored.entityKey === draft.entityKey
                            && stored.tabId === draft.tabId
                            && stored.schemaVersion === draft.schemaVersion
                            && stored.updatedAt === draft.updatedAt
                            && samePayload(draft.kind, stored.payload, draft.payload)
                        )),
                    );
                    if (deleted) {
                        continue;
                    }

                    const latest = await findDraft(draft.id);
                    if (latest && latest.tabId !== context.tabId) {
                        remaining.push(latest);
                    }
                    continue;
                }
                if (draft.tabId === context.tabId) {
                    if (!activeDraftIds.has(context.entityKey)) {
                        activeDraftIds.set(context.entityKey, draft.id);
                    }
                    continue;
                }

                remaining.push(draft);
            }

            if (generation !== recoveryGeneration) {
                return;
            }
            if (remaining.length === 0) {
                hideBanner();
                return;
            }

            showDraft(context, remaining[0], remaining.length);
        } catch (error) {
            showStorageFailure(error);
        } finally {
            if (generation === recoveryGeneration) {
                document.documentElement.dataset.assestmeWorkspaceDraftRecovery = 'complete';
                document.documentElement.dataset.assestmeWorkspaceDraftRecoveryEntity = context.entityKey;
            }
        }
    };

    const rememberStructuralChange = (target) => {
        const collection = target.closest?.('[data-assestme-draft-collection="solutions"]');
        const context = resolveContext();
        if (!collection || !context || context.kind !== 'finding' || context.readOnly) {
            return;
        }

        const key = contextKey(context);
        structuralDraftChanges.set(key, context);
        incrementRevision(key);
        void requestPersistentStorage();
        emit('assestme-local-draft-dirty', { entityKey: context.entityKey });
    };

    const persistStructuralChangeAfterMorph = () => {
        if (structuralDraftChanges.size === 0) {
            return;
        }

        const pending = Array.from(structuralDraftChanges.entries());
        structuralDraftChanges.clear();

        pending.forEach(([key, context]) => {
            void (async () => {
                try {
                    const payload = currentPayload(context);
                    if (saveInProgress) {
                        newerPayloads.set(key, payload);
                    }
                    await persistCurrentDraft(context, payload);
                } catch (error) {
                    showStorageFailure(error);
                }
            })();
        });
    };

    const queueRecovery = () => {
        document.documentElement.dataset.assestmeWorkspaceDraftRecovery = 'pending';
        if (recoveryFrame !== null) {
            window.cancelAnimationFrame(recoveryFrame);
        }
        recoveryFrame = window.requestAnimationFrame(() => {
            recoveryFrame = null;
            void evaluateRecovery();
        });
    };

    const solutionIdentity = (solution) => {
        if (solution?.id !== null && solution?.id !== undefined) {
            return `id:${solution.id}`;
        }
        if (solution?.external_key) {
            return `external:${solution.external_key}`;
        }

        return null;
    };

    const alignSolutionPayload = (liveSolutions, draftSolutions) => {
        const liveIsArray = Array.isArray(liveSolutions);
        const liveEntries = Object.entries(liveSolutions ?? {});
        const draftEntries = Object.entries(draftSolutions ?? {});
        if (liveEntries.length !== draftEntries.length) {
            return { compatible: false, value: liveSolutions };
        }

        const unusedLive = new Map(liveEntries);
        const alignedEntries = draftEntries.map(([, draftSolution]) => {
            const identity = solutionIdentity(draftSolution);
            let matchingEntry = identity === null
                ? null
                : Array.from(unusedLive.entries()).find(([, solution]) => (
                    solutionIdentity(solution) === identity
                ));

            if (!matchingEntry && identity === null) {
                matchingEntry = Array.from(unusedLive.entries()).find(([, solution]) => (
                    solutionIdentity(solution) === null
                )) ?? null;
            }
            if (!matchingEntry) {
                return null;
            }

            const [liveKey, liveSolution] = matchingEntry;
            unusedLive.delete(liveKey);

            return [liveKey, { ...liveSolution, ...draftSolution }];
        });
        if (alignedEntries.some((entry) => entry === null) || unusedLive.size !== 0) {
            return { compatible: false, value: liveSolutions };
        }

        const entries = alignedEntries;
        return {
            compatible: true,
            value: liveIsArray
                ? entries.map(([, solution]) => solution)
                : Object.fromEntries(entries),
        };
    };

    const restoredState = (context, liveState, draftPayload) => {
        const restored = { ...liveState, ...draftPayload };
        if (context.kind !== 'finding' || !Object.hasOwn(draftPayload, 'solutions')) {
            return { compatible: true, value: restored };
        }

        const solutions = alignSolutionPayload(liveState.solutions, draftPayload.solutions);
        return {
            compatible: solutions.compatible,
            value: { ...restored, solutions: solutions.value },
        };
    };

    const restoreDisplayedDraft = async () => {
        const context = resolveContext();
        const selected = displayedDraft;
        if (!context
            || context.readOnly
            || !selected
            || selected.contextKey !== `${context.entityKey}:${context.tabId}`
            || selected.draft.schemaVersion !== DRAFT_SCHEMA_VERSION) {
            return;
        }

        try {
            void requestPersistentStorage();
            const liveState = toPlainObject(context.wire.$get(context.statePath));
            const restoredPayload = toPlainObject(selected.draft.payload);
            const alignedState = restoredState(context, liveState, restoredPayload);
            if (!alignedState.compatible) {
                const element = banner();
                const error = element?.querySelector('[data-assestme-local-draft-error]');
                if (element) {
                    element.dataset.draftStatus = 'stale';
                }
                if (error) {
                    error.textContent = element.dataset.solutionStructureMismatchLabel;
                    error.hidden = false;
                }
                showSaveStatus('stale');
                return;
            }

            await context.wire.$set(context.statePath, alignedState.value, false);
            const adopted = selected.draft.tabId === context.tabId
                ? { ...selected.draft }
                : {
                    ...selected.draft,
                    id: createUuid(),
                    tabId: context.tabId,
                    requestId: createUuid(),
                    attemptedRequestId: null,
                    attemptedAt: null,
                    sourceDraftId: selected.draft.id,
                    sourceDraftTabId: selected.draft.tabId,
                    sourceDraftUpdatedAt: selected.draft.updatedAt,
                    updatedAt: new Date().toISOString(),
                };
            await queueDraftMutation(contextKey(context), () => putDraft(adopted));
            activeDraftIds.set(context.entityKey, adopted.id);
            displayedDraft = { ...selected, draft: adopted, restored: true };
            incrementRevision(contextKey(context));

            const actions = banner()?.querySelector('[data-assestme-local-draft-actions]');
            if (actions) {
                actions.hidden = true;
            }
            emit('assestme-local-draft-restored', {
                entityKey: context.entityKey,
                stale: selected.stale,
            });
            showSaveStatus(navigator.onLine ? (selected.stale ? 'stale' : 'local') : 'offline');
            context.form.querySelector('input, textarea, select')?.focus();
        } catch (error) {
            showStorageFailure(error);
        }
    };

    const discardDisplayedDraft = async () => {
        const context = resolveContext();
        const selected = displayedDraft;
        if (!context
            || !selected
            || selected.restored
            || selected.contextKey !== `${context.entityKey}:${context.tabId}`) {
            return;
        }

        try {
            void requestPersistentStorage();
            const key = contextKey(context);
            const deleted = await queueDraftMutation(
                `${selected.draft.entityKey}:${selected.draft.tabId}`,
                () => deleteDraftIf(selected.draft.id, (stored) => (
                    stored.entityKey === selected.draft.entityKey
                    && stored.tabId === selected.draft.tabId
                    && stored.schemaVersion === selected.draft.schemaVersion
                    && stored.updatedAt === selected.draft.updatedAt
                    && samePayload(selected.draft.kind, stored.payload, selected.draft.payload)
                )),
            );
            if (!deleted) {
                displayedDraft = null;
                await evaluateRecovery();
                return;
            }
            if (activeDraftIds.get(context.entityKey) === selected.draft.id) {
                activeDraftIds.delete(context.entityKey);
            }
            displayedDraft = null;
            await evaluateRecovery();
            if (!displayedDraft) {
                const active = await activeDraftFor(context);
                if (active) {
                    emit('assestme-local-draft-persisted', {
                        entityKey: active.entityKey,
                        stale: active.baseVersion !== context.expectedVersion,
                    });
                } else if (debounceTimers.has(key)
                    || draftMutationQueues.has(key)
                    || newerPayloads.has(key)
                    || revisionFor(key) > 0) {
                    emit('assestme-local-draft-dirty', { entityKey: context.entityKey });
                } else {
                    emit('assestme-local-draft-clean', { entityKey: context.entityKey });
                }
            }
        } catch (error) {
            showStorageFailure(error);
        }
    };

    const uniqueDrafts = (drafts) => Array.from(
        new Map(drafts.map((draft) => [draft.id, draft])).values(),
    );

    const draftForTab = async (entityKey, tabId) => {
        const drafts = await draftsForEntity(entityKey);

        return drafts.find((draft) => (
            draft.tabId === tabId && draft.schemaVersion === DRAFT_SCHEMA_VERSION
        )) ?? null;
    };

    const handleServerConfirmation = async (detail) => {
        const requestId = detail?.requestId;
        const appliedVersion = Number.parseInt(detail?.appliedVersion, 10);
        if (typeof requestId !== 'string' || !Number.isInteger(appliedVersion)) {
            return;
        }

        const attempt = pendingAttempts.get(requestId);
        if (storageUnavailable) {
            if (!attempt) {
                return;
            }

            pendingAttempts.delete(requestId);
            const key = `${attempt.entityKey}:${attempt.tabId}`;
            const evidenceChanged = evidenceRevisionFor(key) > attempt.evidenceRevision;
            if (revisionFor(key) > attempt.revision) {
                showSaveStatus('storage_error', attempt.entityKey);
            } else {
                editRevisions.delete(key);
                newerPayloads.delete(key);
                if (!evidenceChanged) {
                    evidenceRevisions.delete(key);
                }
                emit('assestme-local-draft-clean', {
                    clearEvidence: !evidenceChanged,
                    entityKey: attempt.entityKey,
                    requestId,
                });
            }
            return;
        }

        try {
            const byRequest = await draftsForIndex('requestId', requestId);
            const byAttempt = await draftsForIndex('attemptedRequestId', requestId);
            const expectedTabId = attempt?.tabId ?? resolveContext()?.tabId;
            const matches = uniqueDrafts([...(byRequest ?? []), ...(byAttempt ?? [])])
                .filter((draft) => (
                    draft.assessmentId === Number.parseInt(detail.assessmentId, 10)
                    && draft.kind === detail.kind
                    && (detail.kind !== 'finding'
                        || draft.findingId === Number.parseInt(detail.findingId, 10))
                    && (!expectedTabId || draft.tabId === expectedTabId)
                ));
            let preservedNewerDraft = false;
            const confirmedSources = [];

            for (const draft of matches) {
                const key = `${draft.entityKey}:${draft.tabId}`;
                await queueDraftMutation(key, async () => {
                    const stored = await findDraft(draft.id);
                    if (!stored) {
                        return;
                    }

                    const context = resolveContext();
                    const isCurrentEntity = context
                        && context.entityKey === stored.entityKey
                        && context.tabId === stored.tabId;
                    const hasNewerRevision = attempt
                        && revisionFor(key) > attempt.revision;
                    const capturedPayload = newerPayloads.get(key) ?? null;
                    const latestPayload = capturedPayload
                        ?? (hasNewerRevision && isCurrentEntity ? currentPayload(context) : null);
                    const hasUnpersistedNewerState = hasNewerRevision
                        && latestPayload !== null
                        && !samePayload(stored.kind, stored.payload, latestPayload);

                    if (stored.sourceDraftId && attempt) {
                        confirmedSources.push({
                            entityKey: stored.entityKey,
                            id: stored.sourceDraftId,
                            kind: stored.kind,
                            payload: attempt.payload,
                            tabId: stored.sourceDraftTabId,
                            updatedAt: stored.sourceDraftUpdatedAt,
                        });
                    }

                    if (hasUnpersistedNewerState) {
                        const updated = {
                            ...stored,
                            baseVersion: appliedVersion,
                            requestId: createUuid(),
                            attemptedRequestId: null,
                            attemptedAt: null,
                            payload: latestPayload,
                            updatedAt: new Date().toISOString(),
                        };
                        await putDraft(updated);
                        activeDraftIds.set(updated.entityKey, updated.id);
                        newerPayloads.delete(key);
                        preservedNewerDraft = true;
                        return;
                    }

                    if (stored.attemptedRequestId === requestId && stored.requestId !== requestId) {
                        const updated = {
                            ...stored,
                            baseVersion: appliedVersion,
                            attemptedRequestId: null,
                            attemptedAt: null,
                        };
                        await putDraft(updated);
                        newerPayloads.delete(key);
                        preservedNewerDraft = true;
                        return;
                    }

                    if (stored.requestId === requestId) {
                        await deleteDraft(stored.id);
                        if (activeDraftIds.get(stored.entityKey) === stored.id) {
                            activeDraftIds.delete(stored.entityKey);
                        }
                        if (displayedDraft?.draft.id === stored.id) {
                            displayedDraft = null;
                        }
                    }
                });
            }

            for (const source of confirmedSources) {
                await queueDraftMutation(
                    `${source.entityKey}:${source.tabId}`,
                    () => updateDraftIf(
                        source.id,
                        (stored) => (
                            stored.updatedAt === source.updatedAt
                            && samePayload(source.kind, stored.payload, source.payload)
                        ),
                        (stored) => ({
                            ...stored,
                            confirmedAppliedVersion: appliedVersion,
                            confirmedByRequestId: requestId,
                        }),
                    ),
                );
            }

            pendingAttempts.delete(requestId);
            const confirmedEntityKey = attempt?.entityKey ?? matches[0]?.entityKey ?? null;
            const confirmedTabId = attempt?.tabId ?? matches[0]?.tabId ?? null;
            const confirmedKey = confirmedEntityKey && confirmedTabId
                ? `${confirmedEntityKey}:${confirmedTabId}`
                : null;
            const active = confirmedEntityKey && confirmedTabId
                ? await draftForTab(confirmedEntityKey, confirmedTabId)
                : null;
            const currentContext = resolveContext();
            const activeIsCurrent = active
                && currentContext?.entityKey === active.entityKey
                && currentContext.tabId === active.tabId;
            const hasNewerRevision = Boolean(confirmedKey && attempt
                && revisionFor(confirmedKey) > attempt.revision);
            const hasCapturedNewerPayload = Boolean(confirmedKey
                && newerPayloads.has(confirmedKey));
            const capturedNewerPayload = hasCapturedNewerPayload
                ? newerPayloads.get(confirmedKey)
                : null;
            const activeMatchesLatest = Boolean(active && (
                (activeIsCurrent
                    && samePayload(active.kind, active.payload, currentPayload(currentContext)))
                || (!activeIsCurrent && !hasNewerRevision)
                || (!activeIsCurrent
                    && hasCapturedNewerPayload
                    && samePayload(active.kind, active.payload, capturedNewerPayload))
            ));
            const hasUnprotectedNewerState = hasNewerRevision
                && !activeMatchesLatest;
            const evidenceChanged = Boolean(confirmedKey && attempt
                && evidenceRevisionFor(confirmedKey) > attempt.evidenceRevision);

            if (confirmedKey && !evidenceChanged) {
                evidenceRevisions.delete(confirmedKey);
            }

            if (hasUnprotectedNewerState && confirmedEntityKey) {
                emit('assestme-local-draft-dirty', {
                    clearEvidence: !evidenceChanged,
                    entityKey: confirmedEntityKey,
                });
            } else if (active) {
                if (confirmedKey) {
                    editRevisions.delete(confirmedKey);
                    newerPayloads.delete(confirmedKey);
                }
                emit('assestme-local-draft-persisted', {
                    clearEvidence: !evidenceChanged,
                    entityKey: active.entityKey,
                    stale: active.baseVersion !== appliedVersion,
                });
                if (preservedNewerDraft && currentContext?.entityKey === confirmedEntityKey) {
                    showSaveStatus('local');
                }
            } else if (confirmedEntityKey) {
                if (confirmedKey) {
                    editRevisions.delete(confirmedKey);
                    newerPayloads.delete(confirmedKey);
                }
                emit('assestme-local-draft-clean', {
                    clearEvidence: !evidenceChanged,
                    entityKey: confirmedEntityKey,
                    requestId,
                });
            }
            queueRecovery();
        } catch (error) {
            showStorageFailure(error);
        }
    };

    const handleServerDiscard = async (detail) => {
        const context = resolveContext();
        if (!context
            || detail?.kind !== context.kind
            || Number.parseInt(detail?.assessmentId, 10) !== context.assessmentId
            || Number.parseInt(detail?.findingId, 10) !== context.findingId
            || detail?.tabId !== context.tabId) {
            return;
        }

        const key = contextKey(context);
        const timer = debounceTimers.get(key);
        if (timer !== undefined) {
            window.clearTimeout(timer);
            debounceTimers.delete(key);
        }
        editRevisions.delete(key);
        evidenceRevisions.delete(key);
        newerPayloads.delete(key);
        structuralDraftChanges.delete(key);
        for (const [requestId, attempt] of pendingAttempts) {
            if (attempt.entityKey === context.entityKey && attempt.tabId === context.tabId) {
                pendingAttempts.delete(requestId);
            }
        }

        try {
            await queueDraftMutation(key, async () => {
                const drafts = await draftsForEntity(context.entityKey);
                for (const draft of drafts) {
                    if (draft.tabId === context.tabId) {
                        await deleteDraft(draft.id);
                    }
                }
            });
            activeDraftIds.delete(context.entityKey);
            if (displayedDraft?.contextKey === key) {
                hideBanner();
            }
            emit('assestme-local-draft-clean', {
                clearEvidence: true,
                entityKey: context.entityKey,
            });
            queueRecovery();
        } catch (error) {
            showStorageFailure(error);
        }
    };

    const registerLivewire = () => {
        if (livewireRegistered || typeof window.Livewire?.on !== 'function') {
            return;
        }

        livewireRegistered = true;
        window.Livewire.on('assestme-server-save-confirmed', handleServerConfirmation);
        window.Livewire.on('assestme-server-changes-discarded', handleServerDiscard);
        window.Livewire.hook('morphed', () => {
            persistStructuralChangeAfterMorph();
            queueRecovery();
        });
        queueRecovery();
    };

    document.documentElement.dataset.assestmeWorkspaceDraftsAsset = 'loaded';
    document.addEventListener('input', scheduleDraftWrite);
    document.addEventListener('change', scheduleDraftWrite);
    window.addEventListener('assestme-evidence-dirty', rememberPastedEvidence);
    document.addEventListener('submit', interceptSubmit, true);
    document.addEventListener('click', interceptSaveAndNext, true);
    document.addEventListener('click', (event) => {
        if (event.target.closest?.('[data-assestme-draft-structural-action="solutions"]')) {
            rememberStructuralChange(event.target);
        }
    }, true);
    document.addEventListener('end', (event) => rememberStructuralChange(event.target), true);
    document.addEventListener('click', (event) => {
        if (event.target.closest?.('[data-dusk="restore-local-draft"]')) {
            void restoreDisplayedDraft();
        }
        if (event.target.closest?.('[data-dusk="discard-local-draft"]')) {
            void discardDisplayedDraft();
        }
    });
    document.addEventListener('livewire:init', registerLivewire, { once: true });
    document.addEventListener('livewire:navigated', queueRecovery);
    window.addEventListener('assestme-finding-selected', queueRecovery);
    window.addEventListener('popstate', queueRecovery);
    registerLivewire();
    queueRecovery();
})();
