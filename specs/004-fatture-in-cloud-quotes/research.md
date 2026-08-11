# Research: Fatture in Cloud Quotes

## Sources and baseline

- Repository baseline: `develop` at `653be43f743ac483ce047e0e18aeb0dc4388daa6`.
- Official FIC developer documentation inspected 2026-08-11: authentication/code flow, scopes, errors, pagination, client creation, invoice/document creation, utilities, and response fieldsets.
- Official OpenAPI repository `fattureincloud/openapi-fattureincloud` inspected at `2be805afd6667b00b4f1d09b6833472e8eb09d7c`.
- Official PHP SDK repository `fattureincloud/fattureincloud-php-sdk` inspected at `4f8a5dbe4cd4e8948c8eef1b73017257dd4e3121`.

## Decision: Use OAuth 2.0 Authorization Code with local encrypted token lifecycle

**Rationale**: FIC calls Authorization Code the preferred flow for a backend that can safely store a client secret. Authorization uses `https://api-v2.fattureincloud.it/oauth/authorize`; code exchange and refresh both use `POST https://api-v2.fattureincloud.it/oauth/token`. Access tokens last 24 hours and refresh tokens last one year from last refresh according to the current guide. The callback must compare a random session `state`, and FIC compares `redirect_uri` as an exact string.

Store encrypted client secret, access token, and refresh token plus UTC expiry. Persist the replacement refresh token returned by refresh. Use a small expiry skew. On 401 refresh once; do not loop.

**Alternatives considered**:

- Manual token: rejected because the requested UI explicitly includes OAuth/lifecycle/reconnect.
- Device Code: rejected because it is not generally available and FIC recommends Authorization Code when a backend callback exists.
- Laravel Socialite: rejected because no first-party FIC driver is installed and a custom provider layer adds no value over two documented HTTP exchanges.

## Decision: Request the minimum four scopes

Use `entity.clients:a products:r settings:r issued_documents.quotes:a`.

**Rationale**: `:a` grants the required read/write behavior for client and quote resources; products and VAT types are read-only. `settings:r` is required by `List Vat Types`. No invoice, email, stock, webhook, payment, supplier, or archive permission is needed.

**Alternatives considered**:

- Broad issued-document or settings write scopes: rejected as unnecessary privilege.
- Separate `:r` plus `:a` for the same resource: rejected because FIC defines `:a` as full write permission and examples use it for manipulation.

## Decision: Disconnect is local because the public contract has no revoke endpoint

**Rationale**: Current authentication documentation discusses revoking a connection in FIC, but neither the OpenAPI contract nor OAuth guide defines an HTTP revocation endpoint. AssestMe will erase local tokens/company/default VAT and show Italian guidance/link for provider-side revocation.

**Alternatives considered**:

- Invent `/oauth/revoke`: prohibited and rejected.
- Keep local refresh token after disconnect: rejected because it would make disconnect misleading.

## Decision: Use Laravel HTTP, not the generated SDK

**Rationale**: The feature needs a small endpoint subset, Laravel already supplies an authenticated JSON client and existing `Http::fake`, and concrete typed application DTOs keep the consumed contract visible. The official SDK requires Guzzle packages already present transitively but adds a large generated class/model surface and a separate fake boundary. Avoiding it preserves proportional tests and release size.

**Alternatives considered**:

- Official PHP SDK: valid but discarded for dependency/surface/test cost, not due to provider incompatibility.
- Generic provider interface: rejected because one-implementation interfaces and speculative provider architecture are prohibited.

## Decision: Consume only verified API v2 endpoints

The contract is recorded in [contracts/fatture-in-cloud-v2.md](contracts/fatture-in-cloud-v2.md). The required calls are:

- `GET /user/companies`
- `GET /c/{company_id}/info/vat_types`
- `GET|POST /c/{company_id}/entities/clients`
- `GET /c/{company_id}/entities/clients/{client_id}` for mapping validation
- `GET /c/{company_id}/products`
- `GET /c/{company_id}/products/{product_id}` for selected-product validation
- `GET|POST /c/{company_id}/issued_documents`
- `GET /c/{company_id}/issued_documents/{document_id}`

All list calls honor response pagination. Provider `q` narrows candidates but never replaces exact local comparison.

**Alternatives considered**:

- Product/VAT synchronization: rejected; live read-only data is required.
- Provider totals/pre-create info: rejected; the requested workflow does not need local/provider preview totals or local numbering.

## Decision: Exactly one FIC company

**Rationale**: `GET /user/companies` is the official discovery operation. Accept only one returned company. Zero and more than one are explicit Italian errors; there is no selector or remembered choice.

**Alternatives considered**:

- First company wins: prohibited automatic selection.
- Company selector: explicitly outside requested single-company scope.

## Decision: Exact client identity and portable local mapping

Normalize VAT/tax code for comparison by trimming, removing whitespace and common punctuation, uppercasing, and removing a leading `IT` only from VAT numbers. Search by each available fiscal identifier using official `q`, traverse result pages, and compare returned fields exactly after normalization. Never use name similarity. One candidate maps automatically; multiple exact candidates require explicit administrator selection; zero offers creation.

Store nullable opaque `fic_company_id` and `fic_client_id` on `clients`, with a composite unique constraint. Revalidate with the current company before reuse. Connection changes make a foreign-company mapping unusable without deleting its historical exact identity.

**Alternatives considered**:

- Mapping table: unnecessary for one current mapping per local Client.
- Name fallback: rejected by explicit contract.

## Decision: Keep all commercial state in the composer component

**Rationale**: Public Livewire form arrays survive component requests and support validation without database/session/cache models. Settings store no rows/catalog/version, and the created response is displayed only in current page state.

**Alternatives considered**:

- Quote draft/version models: explicitly prohibited.
- Cache/session commercial persistence: rejected because it would be hidden local persistence and creates stale/recovery semantics not requested.
- IndexedDB draft: rejected; the Workspace recovery contract does not authorize a quote-draft store.

## Decision: Exact marker/reference grammar

- Finding reference: `F-` plus exactly six decimal digits, generated by `sprintf('F-%06d', $id)`; IDs above six digits remain representable by generation but only exact six-digit tokens reconcile under the v1 contract, so creation validates the supported ID range.
- Marker: anchored, case-sensitive `\A\[ASSESTME assessment=([1-9][0-9]*) version=([1-9][0-9]*)\]\z`.

Version lookup parses only `quote` documents for the current Assessment and chooses the largest integer. Row reconciliation extracts exact `F-[0-9]{6}` tokens and checks current non-deleted Finding membership.

**Alternatives considered**:

- JSON in notes/extra fields: not needed and would complicate visible/remote recovery.
- Fuzzy/legacy parsing: explicitly prohibited.

## Decision: Quote payload uses provider-supported document/item fields only

Create `data.type=quote`, mapped `entity`, exact `subject`, Italian `visible_subject`, and `items_list`. Each row uses supported `product_id`, `code`, `name`, `description`, `qty`, `measure`, `net_price`, `discount`, and `vat.id`; product/category/price remain administrator-editable suggestions. FIC remains responsible for numbering, totals, fiscal meaning, and rendered output.

**Alternatives considered**:

- Locally computed VAT/totals: prohibited.
- Persisting the final payload as history: prohibited.

## Decision: Explicit failure and ambiguity rules

- Ordinary non-2xx: bounded Italian provider error; no success and composition retained.
- 401: refresh once and repeat only safe reads or pre-submit validation; if POST response is 401 it is an ordinary rejected response and may be retried only after refresh because the provider definitively responded.
- 429: preserve composition and surface bounded `Retry-After`; no random retries.
- Connection failure before POST dispatch: ordinary failure.
- Connection failure after POST may have been sent: list quote candidates and exact-match the submitted marker. Exactly one real result is success; zero/multiple is unresolved. Never blind-retry the POST.

**Alternatives considered**:

- General retry middleware: rejected because it can duplicate writes and obscures status-specific behavior.
- Local idempotency record: rejected as commercial persistence; exact remote marker is the requested reconciliation key.

## Decision: Proportional verification

Use compact datasets for pure logic, a small number of Feature/Livewire files for configuration/client/composer/version/create/guard, `Http::fake` rather than a custom FIC simulator, and one Dusk happy path. No live-provider tests or credentials. Final convergence reproduces the current workflow jobs exactly where local execution is technically possible.
