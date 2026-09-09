# Reports, evidence, and private files

## Evidence

Evidence is private and belongs to the applicable Finding/assessment context.

- Images, PDFs, documents, logs, links, and other approved evidence types are supported according to server validation.
- Physical names are generated; user-provided names are metadata only.
- Private files are never exposed directly under `public`.
- Downloads are authenticated.
- SHA-256 is stored and verified where the contract requires it.
- Report generation does not omit failed or unreadable evidence silently.
- Evidence presentation keeps title, image, and caption as a logical page-safe block where applicable.
- Evidence submitted from the Finding editor is part of the signed Finding aggregate: all files are inspected before final writes, batch duplicates and aggregate limits fail the whole request, database failure compensates prepared files, and one successful request increments the assessment version once.

## PDF renderer

- Spatie Laravel PDF invokes WeasyPrint as the sole production renderer.
- There is no automatic fallback or parallel renderer.
- One application-owned Blade/CSS report supports mixed A4 orientation.
- Report settings preview uses the same renderer service and `reports.assessment` view as production.
- Preview is transient and creates no immutable generated-file record.

## Authoritative generation

One PDF-generation action:

1. validates the complete assessment;
2. creates the normalized immutable payload and settings snapshot;
3. renders one authoritative private file;
4. stores hashes and metadata;
5. applies configured freeze behavior;
6. immediately downloads that same authenticated hash-verified record;
7. retains it in generated-file history.

It never rerenders a second download copy. Validation or rendering failure remains visible and never triggers success/download.

## Report content

The report remains entrepreneur-oriented while retaining required technical information. It includes:

- cover branding and effective title;
- company/assessment overview;
- general picture and priority legend;
- complete Finding summary;
- Finding details, problem, recommended solution, alternatives, effort/estimate, scope, risk, evidence, notes, and resolution where present;
- generated-file metadata and configured footer/signature elements;
- the fixed VAT-excluded estimate note exactly once.

`cover_title_mode` supports separate and combined title/company presentation. Branding remains consultant, company, or both. Empty optional content produces no placeholder block.
Consultant report logos accept verified PNG, JPEG, and WebP files up to 5 MB. Formats unsupported by
the normative WeasyPrint runtime, including AVIF, are rejected before report generation.

## Pagination and layout

- Cover, overview, Finding, and evidence pages are portrait where defined by the accepted composition.
- Summary uses the approved landscape composition.
- A Finding logical block and designated evidence blocks avoid inappropriate splitting.
- Images preserve proportions and do not crop or overflow A4-safe bounds.
- Page numbers and continuation behavior remain consistent.

## XLSX

PhpSpreadsheet generates the complete textual assessment export. The dedicated exporter is independent from generic Filament table export plugins and follows the same normalized content and VAT-note requirements.

## Optional Google Drive readable copy

Google Drive is a one-way, non-restorable continuity copy, never a database, editing surface,
backup source, or import origin. The managed hierarchy is:

Its OAuth client ID and encrypted write-only secret are configured from the native Settings page,
with optional environment defaults. The page calculates the callback URI and embeds the complete
Google Cloud setup guide. Incomplete configuration keeps the guide/form available and does not
affect local report/evidence behavior. A successful first connection creates a new application-owned
`My Drive/AssestMe` root and stores its returned ID without same-name lookup or adoption.
Every descendant folder, native Sheet, evidence file, and generated report is created with the exact
managed parent ID through the public Drive API; an opaque ID is never treated as a folder name.
Automatic synchronization becomes active as soon as root creation succeeds and remains toggleable.

```text
My Drive/AssestMe/
  C-000001 - <company>/
    A-000012 - YYYY-MM-DD - <assessment>/
      Findings - A-000012
      Documenti/
      Evidenze/
```

The native Sheet owns only the managed ranges in `Assessment`, `Findings`, `Soluzioni`, and
`Evidenze`; each synchronization clears and rewrites those ranges from canonical local data,
freezes table headers, and preserves additional tabs. File evidence is copied unchanged to
`Evidenze`; URL evidence remains a URL row. Every immutable existing GeneratedReport PDF/XLSX is
copied unchanged to `Documenti`. Required local bytes must exist and match stored size plus SHA-256
before the first remote mutation for that assessment.
Null or absent Sheet cells are written as explicit empty cells so Google receives dense value rows
and retains their intended column positions.

Managed objects are rediscovered inside the expected parent by zero-padded local-ID prefix. Zero
matches creates, one match reuses and may rename, and multiple matches fail explicitly. No remote
object is automatically deleted: stale managed copies, the managed root after disconnect,
additional tabs, and unrelated files remain untouched.

The canonical assessment hash includes the application-owned root and every projected local relation,
evidence record, and generated report. Volatile synchronization time and returned remote links are
excluded. Normal execution skips an equal hash before contacting Google; forced execution bypasses
only this comparison.

## Deletion and staged recovery

Filesystem deletion is recoverable staging, not a false SQL/filesystem atomicity claim.

- Destructive resource actions delegate to policy-aware application behavior.
- Standard Filament delete actions that bypass this behavior are not used.
- Committed trash cleanup may remain retryable and must not be reported as silently complete.
- Archive/permanent-delete behavior follows the global setting.

## Backup and restore

Backups use a driver-aware schema-v2 manifest:

- SQLite uses a consistent application-controlled snapshot and mandatory real verification.
- MySQL/MariaDB use operation-time discovery of a product-compatible dump/restore client.
- Missing server-database clients do not block installation, but SQL backup/restore remains unavailable with actionable errors.
- Restore is CLI-only, requires maintenance mode, verifies hashes, creates a safety backup, and performs best-effort compensation.
- Failed import plus failed compensation leaves the application in maintenance mode with diagnostic artifacts.
- No cross-driver data conversion is provided.
