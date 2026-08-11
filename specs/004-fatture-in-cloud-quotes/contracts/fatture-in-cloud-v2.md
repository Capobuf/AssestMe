# Consumed Contract: Fatture in Cloud API v2

This is the narrow outbound contract AssestMe consumes. It is not a public AssestMe API and does not copy the full provider OpenAPI specification.

Verified against official OpenAPI commit `2be805afd6667b00b4f1d09b6833472e8eb09d7c` on 2026-08-11.

## Base and authentication

- Base: `https://api-v2.fattureincloud.it`
- Authorization: `GET /oauth/authorize`
- Token exchange/refresh: `POST /oauth/token`
- API authentication: `Authorization: Bearer <access_token>`
- Scopes: `entity.clients:a products:r settings:r issued_documents.quotes:a`
- Callback state: authenticated-session random value, exact single-use comparison
- No public revocation endpoint was found; local disconnect deletes local credentials and points the administrator to FIC authorization management.

## Endpoints

| User behavior | Method/path | Request subset | Response subset |
|---|---|---|---|
| Discover company | `GET /user/companies` | bearer | `data.companies[].id`, display name/name |
| List VAT types | `GET /c/{company_id}/info/vat_types` | optional `fieldset=detailed` | `data[].id`, `value`, `description`, `is_disabled`, `default` |
| Search clients | `GET /c/{company_id}/entities/clients` | `q`, `fieldset=detailed`, `page`, `per_page` | `data[]` Client fields, `meta.pagination` |
| Validate mapped client | `GET /c/{company_id}/entities/clients/{client_id}` | `fieldset=detailed` | `data.id`, name, VAT/tax code, address fields |
| Create client | `POST /c/{company_id}/entities/clients` | `{data: Client}` | `data.id` plus returned Client fields |
| Search products | `GET /c/{company_id}/products` | `q`, `fieldset=detailed`, `page`, `per_page` | `data[]` Product fields, `meta.pagination` |
| Validate product | `GET /c/{company_id}/products/{product_id}` | `fieldset=detailed` | `data.id`, code/name/description/measure/net price/default VAT |
| List quote candidates | `GET /c/{company_id}/issued_documents` | `type=quote`, `q`, `fieldset=detailed`, pagination | `data[].id/type/subject/visible_subject/url`, pagination |
| Load previous quote | `GET /c/{company_id}/issued_documents/{document_id}` | `fieldset=detailed` | detailed document and `items_list` |
| Create quote | `POST /c/{company_id}/issued_documents` | `{data: IssuedDocument}` | `data.id`, `data.url` when returned, exact subjects/items |

All IDs are treated as opaque after basic scalar validation. All list calls follow provider pagination to `last_page`; no relevant page is silently omitted.

## OAuth requests

Authorization query:

```text
response_type=code
client_id=<configured>
redirect_uri=<exact named callback URL>
scope=entity.clients:a products:r settings:r issued_documents.quotes:a
state=<single-use random session value>
```

Authorization-code JSON:

```json
{
  "grant_type": "authorization_code",
  "client_id": "<configured>",
  "client_secret": "<encrypted server value>",
  "redirect_uri": "<exact callback URL>",
  "code": "<callback code>"
}
```

Refresh JSON uses `grant_type=refresh_token` and `refresh_token` instead of `redirect_uri`/`code`. Successful token responses must contain `access_token`, `refresh_token`, and positive `expires_in`.

## Client create subset

AssestMe maps available local company data to provider-supported Client fields:

```json
{
  "data": {
    "type": "company",
    "name": "Ragione sociale",
    "vat_number": "01234567890",
    "tax_code": "01234567890",
    "email": "amministrazione@example.it",
    "phone": "+39...",
    "address_street": "Via ...",
    "address_postal_code": "00100",
    "address_city": "Roma",
    "address_province": "RM",
    "country": "Italia"
  }
}
```

Only non-empty supported values are sent. The returned `data.id` is mandatory before persisting a mapping.

## Quote create subset

```json
{
  "data": {
    "type": "quote",
    "entity": { "id": 123 },
    "subject": "[ASSESTME assessment=42 version=3]",
    "visible_subject": "Preventivo AssestMe — Titolo assessment — versione 3",
    "items_list": [
      {
        "product_id": 987,
        "code": "SERV-01",
        "name": "Titolo commerciale",
        "description": "Descrizione commerciale\n\nRiferimenti AssestMe: F-000042, F-000117",
        "qty": 1,
        "measure": "ore",
        "net_price": 100,
        "discount": 0,
        "vat": { "id": 22 }
      }
    ]
  }
}
```

Optional fields are omitted when absent. AssestMe does not send local VAT amounts, totals, numbering, payment data, email instructions, or unsupported arbitrary metadata.

## Exact remote-version contract

- Candidate type must equal `quote`.
- `subject` must fully match `\A\[ASSESTME assessment=([1-9][0-9]*) version=([1-9][0-9]*)\]\z`.
- Assessment capture must equal the current local Assessment ID.
- Latest is the greatest version capture; ties/multiple matches for the same exact submission marker are ambiguous.
- `visible_subject`, client name, document number/date, and notes never identify a version.

## Error contract

- `401`: authorization expired/revoked; one controlled refresh path, then reconnect guidance.
- `403`: missing provider permission; bounded Italian permissions message.
- `404`: mapped/provider object unavailable; invalidate current selection/mapping use, do not fake success.
- `422` and other ordinary 4xx/5xx: bounded Italian provider rejection, composition retained.
- `429`: parse bounded numeric/date `Retry-After` when valid and present it; no arbitrary retry.
- Malformed 2xx body: explicit provider-response error.
- Ambiguous connection failure after quote POST: list quotes and full-string match the exact marker. One match succeeds; zero or multiple remain unresolved. The create POST is never blindly repeated.

Raw request/response bodies, bearer tokens, client secrets, refresh tokens, cookies, and OAuth codes are never logged or shown.
