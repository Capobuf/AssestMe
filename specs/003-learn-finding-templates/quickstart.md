# Quickstart Validation: Learn from Assessment Findings

## Prerequisites

- PHP 8.3+ and installed locked Composer dependencies.
- Disposable SQLite test database with milestone risk/category/template seed data.
- No Node, external API, or browser driver is required for focused Feature/Livewire checks.

## Automated focused validation

```bash
php artisan test tests/Feature/FindingTemplateLearningTest.php tests/Feature/WorkspacePageTest.php tests/Feature/AssessmentDomainTest.php tests/Feature/FindingTemplateCrudTest.php
vendor/bin/pint --test app/Actions/Assessments app/Actions/Templates/SaveFindingTemplate.php app/Data/Templates app/Models/Finding.php app/Services/Templates tests/Feature/FindingTemplateLearningTest.php tests/Feature/WorkspacePageTest.php
vendor/bin/phpstan analyse app/Actions/Assessments app/Actions/Templates/SaveFindingTemplate.php app/Data/Templates app/Models/Finding.php app/Services/Templates --memory-limit=1G
```

Expected: creation/link/update tests pass against real database transactions; no external service or
mock generated file participates.

## Required end-to-end scenarios

1. Create a manual complete Finding with evidence and assessment-only state; save it as a template;
   verify only reusable content, source/fingerprint, solution keys, and one version increment.
2. Recreate one existing template semantically with manual solution keys; preview exact; link it;
   verify zero templates created and deterministic solution alignment.
3. Create a near variant; preview no more than three candidates; save anyway as a separate lineage.
4. Copy one template into two assessments; improve and update the first; verify full replacement,
   stable existing solution ID, authoritative new ID, omitted solution soft deletion, and unchanged
   second Finding.
5. Attempt update from the stale second Finding; verify conflict and byte-for-byte unchanged current
   template reusable projection.
6. Clear a linked Finding fingerprint; verify an identical Finding initializes safely and a differing
   Finding is refused.
7. Disable a source and update it without re-enabling; soft-delete it and verify update refusal while
   save-as-new remains valid.
8. Apply manual priority override and verify active-matrix priority becomes the template default;
   use historical non-selectable classification and verify new-template refusal with no remap.
9. Complete/archive the assessment and verify UI absence plus direct server refusal.
10. Force authoritative template validation failure and verify complete rollback.

## Similarity characterization

Run the focused characterization helper/test against `templates/base-findings.it.json` and record:

- total templates: 221;
- same-category threshold: 0.86;
- cross-category title threshold: 0.96;
- candidate pairs at the chosen threshold;
- clearly anomalous false positives after review.

The baseline JSON must remain unchanged.

## Complete repository gate

Use the current canonical application path after the coherent slice and documentation are complete:

```bash
scripts/test-app.sh
```

Set the documented canonical MariaDB test environment before invoking this path.

Do not report physical Edge, Firefox, iOS Safari, Android Chrome, device, or hosting acceptance as
passed without direct execution evidence.
