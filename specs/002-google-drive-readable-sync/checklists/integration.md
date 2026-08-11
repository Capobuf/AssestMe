# Integration Requirements Checklist: Google Drive Readable Sync

**Purpose**: Release-review quality gate for optionality, authority, security, remote projection,
failure/recovery, and infrastructure boundaries
**Created**: 2026-08-11
**Feature**: [spec.md](../spec.md)

**Review depth**: Formal implementation gate
**Actor/timing**: Author and PR reviewer before implementation and release

## Requirement Completeness

- [x] CHK001 Are requirements explicit that Google is completely optional at installation and runtime? [Completeness, Spec §FR-001, §FR-004]
- [x] CHK002 Is the local database/private storage unambiguously the sole source of truth? [Completeness, Spec §FR-002]
- [x] CHK003 Are bidirectional sync, imports, polling, webhooks, merge, and remote conflict resolution explicitly excluded? [Coverage, Spec §FR-003]
- [x] CHK004 Is the prohibition on Google calls in autosave and explicit workspace-save paths stated independently of failure behavior? [Completeness, Spec §FR-005]
- [x] CHK005 Is one native Google Sheet per Assessment distinguished from global, company-level, and XLSX substitutes? [Clarity, Spec §FR-024]
- [x] CHK006 Are file and URL evidence requirements complete and organized only at Assessment level? [Completeness, Spec §FR-020, §FR-029, §FR-031, §FR-032]
- [x] CHK007 Are all current immutable PDF/XLSX records required without regeneration, omission, or placeholders? [Completeness, Spec §FR-033–§FR-035]

## Requirement Clarity

- [x] CHK008 Is the minimum OAuth scope exact and is broad Drive authorization excluded? [Clarity, Spec §FR-009]
- [x] CHK009 Are refresh-token encryption and every prohibited disclosure surface explicitly enumerated? [Clarity, Spec §FR-011–§FR-012]
- [x] CHK010 Is automatic root creation defined as a new My Drive folder whose returned ID is authoritative, with no same-name adoption? [Clarity, Spec §FR-013–§FR-015]
- [x] CHK011 Are stable local ID prefixes required for every ambiguity-prone Drive object name? [Clarity, Spec §FR-021–§FR-023]
- [x] CHK012 Is duplicate managed-prefix behavior defined as explicit failure rather than first-match selection? [Clarity, Spec §FR-023]
- [x] CHK013 Are the exact four managed Sheet tabs and their row granularity defined without JSON compression? [Clarity, Spec §FR-025–§FR-030]
- [x] CHK014 Is forced synchronization limited to bypassing only content-hash comparison? [Clarity, Spec §FR-040]

## Requirement Consistency

- [x] CHK015 Are overwrite-from-local requirements consistent with preservation of user-added tabs and unrelated files? [Consistency, Spec §FR-003, §FR-030, §FR-036]
- [x] CHK016 Are managed-root creation failure and retry consistent with disabled synchronization and explicit connected-without-root state? [Consistency, Spec §FR-017, SC-006]
- [x] CHK017 Are disconnect requirements consistent with non-deletion and explicit remote-revocation failure? [Consistency, Spec §FR-018–§FR-019, §FR-036]
- [x] CHK018 Are scheduler and manual synchronization governed by the same concurrency and error rules? [Consistency, Spec §FR-042–§FR-046]
- [x] CHK019 Are local availability requirements consistent across OAuth, token, Drive, Sheets, and file failures? [Consistency, Spec §FR-004, §FR-043, §FR-049]

## Acceptance Criteria Quality

- [x] CHK020 Can the realistic dataset outcome be objectively counted for folders, Sheet tabs, reports, and evidence? [Measurability, SC-002]
- [x] CHK021 Can unchanged-assessment behavior be proven by exactly zero Google calls? [Measurability, SC-004]
- [x] CHK022 Can failure/retry success be measured from unchanged prior hash, stored error, retry, and local operation? [Measurability, SC-005]
- [x] CHK023 Is the maximum chunk size quantified and overlap behavior objectively observable? [Measurability, SC-008]
- [x] CHK024 Does acceptance distinguish fake-based automated evidence from real Google verification? [Traceability, SC-009]

## Scenario and Edge-Case Coverage

- [x] CHK025 Are revoked token, inaccessible/deleted root, quota, timeout, Drive, Sheets, unreadable file, and duplicate-prefix failures all specified? [Coverage, Spec §FR-049]
- [x] CHK026 Are failed-sync retry semantics complete: no successful-hash advance, error stored, pending retained, later retry? [Recovery, Spec §FR-042–§FR-043]
- [x] CHK027 Are manually modified managed values explicitly overwritten while local data remains unchanged? [Conflict, US2/AC6]
- [x] CHK028 Are zero-content assessments and soft-deleted local children addressed without inventing remote deletion? [Edge Case, Spec §Edge Cases, §FR-036]
- [x] CHK029 Are callback denial, invalid state, absent refresh token, and missing account email covered? [Exception Flow, Spec §FR-010]
- [x] CHK030 Are concurrent scheduler/manual runs specified without distributed locking infrastructure? [Coverage, US3/AC7, §FR-046]

## Dependencies and Prohibited Expansion

- [x] CHK031 Are the approved dependency roles and absence of a proprietary filesystem/REST client captured by plan decisions? [Dependency, Plan §Technical Context, Research §Dependency compatibility]
- [x] CHK032 Are Node/npm, Redis/worker, service account, generic cloud providers, and a second spreadsheet engine explicitly excluded? [Boundary, Spec §FR-053]
- [x] CHK033 Is Shared Drive support explicitly outside V1 rather than implicitly claimed? [Boundary, Spec §FR-050]
- [x] CHK034 Are tests required to fake Google boundaries and include both failure and retry without real CI credentials? [Coverage, Spec §FR-051–§FR-052]
- [x] CHK035 Is installation-incomplete explicitly an editable Settings state rather than a terminal unavailable state? [Completeness, Spec §US1/AC1–AC2, §FR-006–§FR-007]
- [x] CHK036 Is the client secret encrypted, write-only on reload, and safely retained when its form field is blank? [Security, Spec §FR-011, §FR-016]
- [x] CHK037 Does an OAuth client ID/secret change clear connection/root and require explicit reconnection? [Consistency, Spec §US1/AC8, §FR-016A]
- [x] CHK038 Can the complete two-field application configuration be saved and used with zero `GOOGLE_*` environment values? [Measurability, Spec §SC-001]
- [x] CHK039 Are the calculated read-only callback and its copy action unambiguously required? [Clarity, Spec §FR-006]
- [x] CHK040 Is the embedded six-step guide complete, native, visible when incomplete, and still reachable after configuration? [Completeness, Spec §FR-047A]
- [x] CHK041 Are Google Picker, API key, project number, browser token endpoint, and arbitrary folder selection all explicitly absent? [Boundary, Spec §FR-013]
- [x] CHK042 Is automatic synchronization explicitly enabled by default only after successful managed-root creation and still user-toggleable? [Clarity, Spec §FR-017A]
- [x] CHK043 Must every created object use the exact opaque parent ID without display-path interpretation? [Boundary, Spec §FR-020A]
- [x] CHK044 Are nullable Sheet cells required to preserve dense column positions? [Failure prevention, Spec §FR-030A]
- [x] CHK042 Are dependency edits, forks, patches, monkey-patches, copied vendor classes, and non-public implementation details prohibited? [Boundary, Spec §FR-054]

## Notes

- All 42 requirement-quality checks passed against the specification and plan on 2026-08-11.
- Explicit must-haves incorporated: optional Google, local authority, no autosave calls, no
  bidirectional sync/deletion, per-Assessment Sheet/evidence, ID-bearing names, encrypted token,
  minimum scope, automatic application-owned root, embedded setup guide, no selector/key/project,
  dependency integrity, no Node/Redis worker, explicit errors, preservation of unrelated remote
  files, and failure/retry tests.
