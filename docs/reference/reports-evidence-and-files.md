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

## Pagination and layout

- Cover, overview, Finding, and evidence pages are portrait where defined by the accepted composition.
- Summary uses the approved landscape composition.
- A Finding logical block and designated evidence blocks avoid inappropriate splitting.
- Images preserve proportions and do not crop or overflow A4-safe bounds.
- Page numbers and continuation behavior remain consistent.

## XLSX

PhpSpreadsheet generates the complete textual assessment export. The dedicated exporter is independent from generic Filament table export plugins and follows the same normalized content and VAT-note requirements.

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
