# Repository layout

## Required project artifacts

The application repository retains the implementation artifacts required by the former specification, including:

```text
AGENTS.md
plan.md                         # compatibility index only
README.md
docs/index.md
docs/adr/
docs/explanation/
docs/how-to/
docs/reference/
docs/_meta/
.gitignore
.env.example
composer.json
composer.lock
phpstan.neon
pint.json
schemas/finding-template.schema.json
schemas/finding-template-v1.schema.json
templates/base-findings.it.json
scripts/build-base-findings.php
resources/lang/it/
resources/views/reports/assessment.blade.php
resources/views/reports/partials/
tests/
tests/Browser/
.github/workflows/ci.yml
scripts/preflight.sh
scripts/bootstrap-local.sh
scripts/check.sh
scripts/test-app.sh
scripts/build-release.sh
scripts/release-installer-acceptance.sh
scripts/shared-hosting-smoke.sh
docker/compose.dev.yml
docker/dev/Dockerfile
docker/dev/php.ini
docker/dev/entrypoint.sh
fixtures/imports/
fixtures/reports/
fixtures/evidence/
```

Hosting/release implementation may additionally contain the approved release builder, installer, shared-hosting template, smoke checks, and operator guides already present in the repository.

## Documentation rules

- Do not duplicate product or architecture contracts in new Markdown files.
- Use existing canonical pages when possible.
- ADRs are for durable architectural decisions, not ordinary UI adjustments or bug fixes.
- `_meta` records evidence and traceability, not new product requirements.
- Screenshots and generated QA artifacts remain untracked under the approved storage paths.
- Code comments explain non-obvious implementation reasons.
