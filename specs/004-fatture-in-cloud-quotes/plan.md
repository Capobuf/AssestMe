# Implementation Plan: Fatture in Cloud Quotes

**Branch**: `develop` | **Date**: 2026-08-11 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/004-fatture-in-cloud-quotes/spec.md`

## Summary

Add an optional, Italian-only Fatture in Cloud API v2 integration that lets the singleton administrator securely connect exactly one provider company, enter a transient quote composer through the authoritative Workspace save guard, resolve the Assessment company to an exact FIC client, manually map Findings into commercial rows, optionally use live products/VAT types, load an exact previous remote version, and create/reconcile one real remote quote. Refine the existing composer into a responsive three-area workbench that presents internal groups as Finding rows, keeps products before editable suggestions, and derives a compact transient summary. Only connection settings and the local Client-to-FIC Client mapping persist; the full commercial composition remains Livewire state and FIC remains authoritative.

## Technical Context

**Language/Version**: PHP web and CLI 8.3.0+ (current host 8.3.6)

**Primary Dependencies**: Laravel 13, Filament 5, Livewire 4, Spatie Laravel Settings, Laravel HTTP client; no new Composer dependency

**Storage**: Existing portable SQLite/MySQL/MariaDB schema plus encrypted settings; transient composer arrays only for commercial state

**Testing**: Pest/PHPUnit feature and unit tests, `Http::fake`, existing Livewire/Filament helpers, and one Laravel Dusk happy path

**Target Platform**: Existing Docker Compose development runtime and prebuilt PHP-hosting release

**Project Type**: Single-user Laravel monolith with Filament administration UI

**Performance Goals**: Expose 50 Findings without automatic grouping; provider list methods paginate at their supported maximum; avoid duplicate provider requests during one action; no background work

**Constraints**: Optional integration; Italian UI only; one provider company; no local commercial persistence; no automatic grouping; no fuzzy matching; no provider calls in bootstrap/ordinary unrelated flows; no Node/Redis/worker/live-provider tests

**Scale/Scope**: One administrator, multiple AssestMe Clients/Assessments, one connected FIC company, 50-Finding evidence target, bounded interactive catalogs and provider pagination

## Constitution Check

*GATE: Passed before research and re-checked after design.*

- **Repository authority — PASS**: the plan follows accepted stack, Workspace, storage, security, and verification decisions. The explicit feature instruction authorizes a narrow supersession of the former quotation/VAT exclusion; an accepted ADR update will record that AssestMe may compose a transient remote quote while still performing no local fiscal calculations or commercial persistence.
- **Deliberate simplicity — PASS**: one concrete provider service uses Laravel HTTP directly. No SDK dependency, interface, repository, generic provider abstraction, queue, worker, or catalog module is added.
- **Fixed stack — PASS**: Laravel/Filament/Livewire and all three databases remain supported. No frontend build or external runtime service is introduced.
- **Authoritative persistence/failure — PASS**: the Workspace save gate remains authoritative; encrypted settings and exact mapping are the only new persistence; provider failures and ambiguous POST outcomes remain explicit.
- **Vertical delivery/proportional evidence — PASS**: V1–V6 each joins minimal persistence, concrete API behavior, UI, failure handling, tests, and narrowly affected docs; V7 only converges and reruns current CI equivalents.

## Project Structure

### Documentation (this feature)

```text
specs/004-fatture-in-cloud-quotes/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── fatture-in-cloud-v2.md
├── checklists/
│   └── requirements.md
└── tasks.md
```

### Source Code (repository root)

```text
app/
├── Actions/FattureInCloud/          # typed vertical business actions
├── Data/FattureInCloud/             # bounded immutable provider/composer DTOs
├── Filament/Pages/                   # settings page
├── Filament/Resources/Assessments/Pages/
│   ├── WorkspaceAssessment.php       # guarded entry action only
│   └── CreateFattureInCloudQuote.php # transient composer page
├── Http/Controllers/FattureInCloud/ # OAuth redirect/callback
├── Models/Client.php                 # exact provider mapping fields
├── Services/FattureInCloud/          # OAuth/token/API and exact marker/mapping behavior
└── Settings/FattureInCloudSettings.php

database/
├── migrations/                       # portable Client mapping columns
└── settings/                          # optional encrypted integration settings

resources/
├── lang/it/assestme.php              # Italian-only feature copy
└── views/filament/                    # settings/composer views

routes/web.php                         # authenticated OAuth routes

tests/
├── Unit/FattureInCloud/               # compact pure-logic datasets
├── Feature/FattureInCloud/            # configuration/client/composer/version/create/guard
└── Browser/FattureInCloudQuoteTest.php # one fake-provider happy path
```

**Structure Decision**: Extend the existing Laravel monolith in its established Actions/Data/Services/Filament layout. Keep pages/controllers thin and use concrete typed services/actions with direct Eloquent access.

## Vertical Slices

### V1 — Connect Fatture in Cloud

Deliver the complete `Impostazioni > Fatture in Cloud` flow: encrypted/write-only credentials and tokens, session-bound OAuth state, exact callback URI, authorization-code exchange, access-token expiry/refresh with rotated refresh-token persistence, company discovery with exact-one enforcement, live VAT types/default selection, connection status, verify/reconnect/local disconnect, bounded Italian errors, focused settings/OAuth/company/VAT tests, and the narrow ADR/reference contract update. No consumerless OAuth client is left behind.

### V2 — Enter composer and resolve client

Add the editable-only Workspace action and composer route/page. The action calls the existing private pre-action save protocol and navigates only on success. Composer readiness loads the Assessment with Client/Findings, validates the current FIC company, revalidates an exact persisted mapping, searches all relevant FIC client pages by VAT number/tax code with normalized exact comparison only, exposes explicit selection when multiple exact candidates remain, and creates/maps a provider client when none exists. Focused tests prove dirty-save gating and the mapping/search/create paths.

### V3 — Compose manually

On the real composer page expose Problem/solution context and `F-%06d` references, temporary groups, repeated Finding assignments across groups, free rows, editable commercial fields, and an explicit compatible-exact sum helper. Initial state contains no groups/assignments and `bundled` has no special grouping behavior. Map one manual group/free row to one transient composer row and generate sorted unique references in descriptions. Feature/component tests cover all required composer decisions; nothing commercial is migrated or saved.

### V4 — Enrich with FIC products and VAT

Use paginated read-only product search and live VAT types in the same composer. Product selection retains `product_id`/`code`, suggests supported editable fields, default VAT initializes new rows, and per-row override remains explicit. Final validation rejects unavailable/disabled identities. Tests use `Http::fake`; no product/VAT module, cache table, sync, or tax engine is created. V3 and V4 may share one implementation cycle if the live form remains simpler that way.

### V5 — Previous remote version

List quote documents through official pagination, parse only the full case-sensitive marker grammar, select the greatest positive version for the Assessment, and show an Italian banner with `Carica precedente`/`Parti da zero`. Fetch detailed document data only when loading; reconstruct transient rows and link only exact `F-xxxxxx` tokens belonging to current non-deleted Findings. Preserve unmatched rows as editable/unlinked content. Tests cover exact latest selection, load, and no legacy/fuzzy guessing.

### V6 — Create/reconcile quote

Validate the current live client/VAT/product/Finding state and map one composer row to one FIC `items_list` item. Create type `quote` with exact marker in `subject`, Italian `visible_subject`, resolved entity, and supported row fields. Use a single-flight page flag, explicit ordinary/auth/rate-limit failure states, one token refresh/retry after 401, and exact-marker reconciliation only for an ambiguous connection failure after POST. Report success only with the real provider ID and returned URL when present; persist no quote history. Add compact fake-provider success/error/ambiguous tests and one Dusk happy path spanning dirty Workspace to success.

### V6a — Refine composer UI

Keep all V2–V6 behavior and transient state unchanged while reorganizing the composer into client/previous context above a responsive `Finding e soluzioni | Righe del preventivo | Riepilogo` workbench. Present internal `group` rows as `Riga da finding`, add transient Finding search, derived counts, and a pure display-only VAT-excluded net-row total, place the existing on-demand product control first, keep Problem/solutions and Finding assignments reachable, and retain one final create action with the existing failure, ambiguous-outcome, and confirmed-success states. Use existing Livewire, Filament, translation, and CSS primitives only; add no persistence, provider behavior, dependency, or frontend build step.

### V6b — Apply annotated composer details

Apply the approved headings, capitalization, breadcrumb, prominent client identity, placeholder-only accessible Finding search, and proportional five-control commercial row. Reuse the live read-only product response as the source of editable measure suggestions because its detailed representation already contains `measure`; do not invent a provider endpoint or local catalog. On explicit Finding assignment, prefill editable title and Problem only for a single Finding, keep exact references visible in the row description, and leave the multi-Finding title blank. Display requested euro suffixes without introducing tax computation, currency persistence, or provider request changes.

### V7 — Convergence and regression

Review the tree for hidden commercial persistence, automatic grouping, invented API behavior, excess fake/test infrastructure, untranslated UI, and incomplete vertical links. Update canonical product/domain/UI/security/testing documents without duplicating the spec. Run focused Pint/PHPStan/tests first, then reproduce the current `.github/workflows/quality.yml` jobs: quality without Dusk, isolated Dusk, all four current DB matrix entries, CloudPanel release, clean-checkout bootstrap, and locally applicable publish/deploy script checks. GitHub artifact/release and real CloudPanel deployment remain explicitly NOT_RUN unless actually executed.

## Design Decisions

- **HTTP boundary**: Use Laravel HTTP rather than the official generated PHP SDK. The application needs eight small endpoints, already owns Guzzle transitively, and `Http::fake` gives a proportional existing test boundary. The SDK would add generated surface/dependency weight without changing the provider contract.
- **Token lifecycle**: Store encrypted access/refresh tokens and UTC expiry. Refresh before expiry; persist every returned replacement refresh token. On 401 refresh once and repeat the idempotent GET or pre-POST validation request; never blindly repeat an ambiguous POST.
- **Disconnect**: Clear local credentials/company/default VAT and access state. Current official sources expose no revocation endpoint, so UI links/explains provider-side authorization removal rather than fabricating a call.
- **Client mapping**: Add nullable opaque FIC company/client identifiers to `clients`, with a portable uniqueness constraint preventing one remote client from mapping to two local Clients. A mapping is usable only after live validation against the currently connected company.
- **Commercial state**: Public Livewire form state is the only composition store. No model, migration, settings property, cache record, local draft, or success record represents quote rows/versions/totals.
- **Exact identifiers**: Finding reference is `F-%06d`; version marker is `[ASSESTME assessment=<id> version=<positive-integer>]`; parsing is anchored and case-sensitive.
- **Provider pagination**: Follow response pagination through `last_page`; use official `q` only to reduce candidate sets and always apply exact client-side comparison/marker parsing.
- **Authorization**: Only the singleton authenticated administrator reaches settings, OAuth, Workspace, or composer. OAuth `state` is random, stored in the authenticated session, single-use, and compared in constant time.
- **Failure handling**: Provider responses are converted into bounded Italian categories without provider body/secrets. `Retry-After` is surfaced for 429. Only a post-dispatch connection failure is ambiguous and eligible for marker reconciliation.

## Constitution Check — Post Design

All five gates remain PASS. The model adds only configuration and exact mapping data; actions/services are application-specific; pages stay thin; the design requires no stack exception. The only accepted-decision change is the user-authorized narrow replacement of D-014/D-039, recorded as remote-composition permission with FIC retaining fiscal authority and no local VAT computation.

## Complexity Tracking

No constitution violations require justification.
