<?php

declare(strict_types=1);

namespace App\Filament\Resources\Assessments\Pages;

use App\Actions\FattureInCloud\CreateFattureInCloudQuote as CreateQuoteAction;
use App\Actions\FattureInCloud\FattureInCloudFiscalIdentifier;
use App\Actions\FattureInCloud\FattureInCloudQuoteLogic;
use App\Actions\FattureInCloud\FindPreviousFattureInCloudQuote;
use App\Actions\FattureInCloud\MapFattureInCloudClient;
use App\Actions\FattureInCloud\ResolveFattureInCloudClient;
use App\Data\FattureInCloud\FattureInCloudClientData;
use App\Data\FattureInCloud\FattureInCloudProductData;
use App\Data\FattureInCloud\FattureInCloudVatTypeData;
use App\Enums\AssessmentStatus;
use App\Filament\Pages\FattureInCloudSettingsPage;
use App\Filament\Resources\Assessments\AssessmentResource;
use App\Models\Assessment;
use App\Models\Finding;
use App\Models\FindingSolution;
use App\Services\FattureInCloud\FattureInCloudAmbiguousOutcome;
use App\Services\FattureInCloud\FattureInCloudApi;
use App\Services\FattureInCloud\FattureInCloudConfiguration;
use App\Services\FattureInCloud\FattureInCloudException;
use App\Settings\FattureInCloudSettings;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class CreateFattureInCloudQuote extends ViewRecord
{
    protected static string $resource = AssessmentResource::class;

    protected string $view = 'filament.resources.assessments.pages.create-fatture-in-cloud-quote';

    protected Width|string|null $maxContentWidth = Width::Full;

    public ?string $resolvedClientId = null;

    public ?string $resolvedClientName = null;

    /** @var list<array{id: string, name: string, vat_number: string|null, tax_code: string|null}> */
    public array $clientCandidates = [];

    public bool $clientResolutionFailed = false;

    /** @var list<array{id: int, reference: string, title: string, problem: string, solutions: list<array{id: int, title: string, description: string, estimate: string}>}> */
    public array $findings = [];

    public string $findingSearch = '';

    /** @var list<array{id: string, kind: string, title: string, description: string, net_price: string, quantity: string, measure: string, discount: string, vat_type_id: string, product_id: string|null, product_code: string|null, finding_ids: list<int>, solution_ids: list<int>, unmatched: bool}> */
    public array $rows = [];

    /** @var list<array{id: string, label: string}> */
    public array $vatTypes = [];

    /** @var list<array{id: string, label: string, measure: string|null}> */
    public array $products = [];

    public bool $vatLoadFailed = false;

    public bool $productSearchFailed = false;

    public bool $providerReady = false;

    public ?string $previousDocumentId = null;

    public ?int $previousVersion = null;

    public bool $previousDiscoveryFailed = false;

    public bool $submissionInProgress = false;

    public string $submissionStatus = 'idle';

    public ?string $submittedDocumentId = null;

    public ?string $submittedDocumentUrl = null;

    public ?string $submissionMessage = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        abort_unless($this->assessment()->status === AssessmentStatus::Draft, 403);
        $this->providerReady = $this->ready();
        if (! $this->providerReady) {
            return;
        }
        $this->loadFindings();
        $this->loadVatTypes();
        $this->resolveClient();
        $this->discoverPreviousVersion();
    }

    public function getTitle(): string
    {
        return __('assestme.fatture_in_cloud.composer.title');
    }

    public function getSubheading(): string
    {
        return $this->assessment()->title;
    }

    public function getBreadcrumb(): string
    {
        return __('assestme.fatture_in_cloud.composer.breadcrumb');
    }

    public function settingsUrl(): string
    {
        return FattureInCloudSettingsPage::getUrl();
    }

    public function resolveClient(): void
    {
        $this->clientResolutionFailed = false;
        try {
            $resolution = app(ResolveFattureInCloudClient::class)($this->assessment()->client);
            $this->resolvedClientId = $resolution->client?->id;
            $this->resolvedClientName = $resolution->client?->name;
            $this->clientCandidates = array_map(
                static fn (FattureInCloudClientData $client): array => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'vat_number' => $client->vatNumber,
                    'tax_code' => $client->taxCode,
                ],
                $resolution->candidates,
            );
        } catch (Throwable) {
            $this->clientResolutionFailed = true;
        }
    }

    public function selectClient(string $clientId): void
    {
        $candidate = collect($this->clientCandidates)->firstWhere('id', $clientId);
        if (! is_array($candidate)) {
            return;
        }
        $settings = app(FattureInCloudSettings::class);
        $companyId = $settings->company_id;
        if (! is_string($companyId)) {
            return;
        }
        $remote = app(FattureInCloudApi::class)->client($companyId, $clientId);
        if (! $remote instanceof FattureInCloudClientData || ! $this->matchesLocalFiscalIdentity($remote)) {
            Notification::make()->danger()->title(__('assestme.fatture_in_cloud.composer.client_invalid'))->send();

            return;
        }
        app(MapFattureInCloudClient::class)($this->assessment()->client, $remote);
        $this->resolvedClientId = $remote->id;
        $this->resolvedClientName = $remote->name;
        $this->clientCandidates = [];
    }

    public function createClient(): void
    {
        try {
            $remote = app(ResolveFattureInCloudClient::class)->create($this->assessment()->client);
            $this->resolvedClientId = $remote->id;
            $this->resolvedClientName = $remote->name;
            $this->clientCandidates = [];
            Notification::make()->success()->title(__('assestme.fatture_in_cloud.composer.client_created'))->send();
        } catch (Throwable) {
            Notification::make()->danger()->title(__('assestme.fatture_in_cloud.composer.client_create_failed'))->send();
        }
    }

    public function addGroup(): void
    {
        $this->rows[] = $this->emptyRow('group');
    }

    public function addFreeRow(): void
    {
        $this->rows[] = $this->emptyRow('free');
    }

    public function removeRow(int $index): void
    {
        if (isset($this->rows[$index])) {
            unset($this->rows[$index]);
            $this->rows = array_values($this->rows);
        }
    }

    public function moveRow(int $index, int $direction): void
    {
        $target = $index + $direction;
        if (! isset($this->rows[$index], $this->rows[$target])) {
            return;
        }
        [$this->rows[$index], $this->rows[$target]] = [$this->rows[$target], $this->rows[$index]];
    }

    public function suggestRowPrice(int $rowIndex): void
    {
        $suggestion = $this->compatibleRowPrice($rowIndex);
        if ($suggestion === null) {
            $this->priceSuggestionUnavailable();

            return;
        }
        $this->rows[$rowIndex]['net_price'] = self::decimal($suggestion);
    }

    /** @return list<array{id: int, reference: string, title: string, problem: string, solutions: list<array{id: int, title: string, description: string, estimate: string}>}> */
    public function filteredFindings(): array
    {
        $query = Str::lower(trim($this->findingSearch));
        if ($query === '') {
            return $this->findings;
        }

        return array_values(array_filter(
            $this->findings,
            static fn (array $finding): bool => Str::contains(Str::lower(implode("\n", [
                $finding['reference'],
                $finding['title'],
                $finding['problem'],
            ])), $query),
        ));
    }

    public function isFindingLinked(int $findingId): bool
    {
        foreach (array_keys($this->rows) as $rowIndex) {
            if ($this->rowIncludesFinding($rowIndex, $findingId)) {
                return true;
            }
        }

        return false;
    }

    public function rowIncludesFinding(int $rowIndex, int $findingId): bool
    {
        return isset($this->rows[$rowIndex])
            && in_array($findingId, array_map('intval', $this->rows[$rowIndex]['finding_ids']), true);
    }

    public function updateRowFinding(int $rowIndex, int $findingId, bool $selected): void
    {
        if (! isset($this->rows[$rowIndex]) || $this->rows[$rowIndex]['kind'] !== 'group'
            || ! in_array($findingId, array_column($this->findings, 'id'), true)) {
            return;
        }

        $findingIds = array_values(array_unique(array_map('intval', $this->rows[$rowIndex]['finding_ids'])));
        if ($selected && ! in_array($findingId, $findingIds, true)) {
            $findingIds[] = $findingId;
        } elseif (! $selected) {
            $findingIds = array_values(array_filter(
                $findingIds,
                static fn (int $candidate): bool => $candidate !== $findingId,
            ));
        }
        sort($findingIds, SORT_NUMERIC);
        $this->rows[$rowIndex]['finding_ids'] = $findingIds;
        $this->prefillRowFromFindings($rowIndex);
    }

    /** @return list<string> */
    public function productMeasures(): array
    {
        $measures = [];
        foreach ($this->products as $product) {
            if (is_string($product['measure']) && trim($product['measure']) !== '') {
                $measure = trim($product['measure']);
                $measures[$measure] = $measure;
            }
        }
        sort($measures, SORT_NATURAL | SORT_FLAG_CASE);

        return $measures;
    }

    /** @return array{total: int, finding_rows: int, free_rows: int, linked_findings: int, total_findings: int} */
    public function rowSummary(): array
    {
        $findingRows = 0;
        $freeRows = 0;
        $linkedFindingIds = [];
        $knownFindingIds = array_column($this->findings, 'id');

        foreach ($this->rows as $row) {
            if ($row['kind'] === 'group') {
                $findingRows++;
            } else {
                $freeRows++;
            }
            foreach ($row['finding_ids'] as $findingId) {
                $normalizedFindingId = (int) $findingId;
                if (in_array($normalizedFindingId, $knownFindingIds, true)) {
                    $linkedFindingIds[$normalizedFindingId] = true;
                }
            }
        }

        return [
            'total' => count($this->rows),
            'finding_rows' => $findingRows,
            'free_rows' => $freeRows,
            'linked_findings' => count($linkedFindingIds),
            'total_findings' => count($this->findings),
        ];
    }

    public function rowHasPriceSuggestion(int $rowIndex): bool
    {
        return $this->compatibleRowPrice($rowIndex) !== null;
    }

    public function formattedNetTotal(): string
    {
        $total = FattureInCloudQuoteLogic::transientNetTotal($this->rows);

        return $total === null ? '—' : '€ '.number_format($total, 2, ',', '.');
    }

    public function searchProducts(string $query = ''): void
    {
        $companyId = app(FattureInCloudSettings::class)->company_id;
        if (! is_string($companyId)) {
            return;
        }
        $this->productSearchFailed = false;
        try {
            $this->products = array_map(
                static fn (FattureInCloudProductData $product): array => [
                    'id' => $product->id,
                    'label' => trim(($product->code === null ? '' : $product->code.' — ').$product->name),
                    'measure' => $product->measure,
                ],
                app(FattureInCloudApi::class)->products($companyId, trim($query)),
            );
        } catch (Throwable) {
            $this->products = [];
            $this->productSearchFailed = true;
        }
    }

    public function discoverPreviousVersion(): void
    {
        $this->previousDiscoveryFailed = false;
        try {
            $document = app(FindPreviousFattureInCloudQuote::class)->latest($this->assessment());
            $marker = $document === null ? null : FattureInCloudQuoteLogic::parseMarker($document->subject);
            $this->previousDocumentId = $document?->id;
            $this->previousVersion = $marker['version'] ?? null;
        } catch (Throwable) {
            $this->previousDiscoveryFailed = true;
            $this->previousDocumentId = null;
            $this->previousVersion = null;
        }
    }

    public function loadPreviousVersion(): void
    {
        if ($this->previousDocumentId === null) {
            return;
        }
        try {
            $loaded = app(FindPreviousFattureInCloudQuote::class)->load(
                $this->assessment(),
                $this->previousDocumentId,
            );
            $this->rows = array_map(
                static fn (array $row): array => ['id' => (string) Str::uuid()] + $row,
                $loaded,
            );
            $this->previousDocumentId = null;
        } catch (Throwable) {
            Notification::make()->danger()
                ->title(__('assestme.fatture_in_cloud.composer.previous_load_failed'))
                ->send();
        }
    }

    public function startFromZero(): void
    {
        $this->rows = [];
        $this->previousDocumentId = null;
    }

    public function submitQuote(): void
    {
        if ($this->submissionInProgress || $this->submissionStatus === 'success'
            || $this->resolvedClientId === null) {
            return;
        }
        $this->submissionInProgress = true;
        $this->submissionStatus = 'submitting';
        $this->submissionMessage = null;
        $this->resetErrorBag();
        try {
            $document = app(CreateQuoteAction::class)(
                $this->assessment(),
                $this->resolvedClientId,
                $this->rows,
            );
            $this->submittedDocumentId = $document->id;
            $this->submittedDocumentUrl = $document->url;
            $this->submissionStatus = 'success';
            $this->submissionMessage = __('assestme.fatture_in_cloud.composer.created', [
                'id' => $document->id,
            ]);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                foreach ($messages as $message) {
                    $this->addError($key, $message);
                }
            }
            $this->submissionStatus = 'error';
            $this->submissionMessage = __('assestme.fatture_in_cloud.composer.validation_failed');
        } catch (FattureInCloudAmbiguousOutcome) {
            $this->submissionStatus = 'ambiguous';
            $this->submissionMessage = __('assestme.fatture_in_cloud.errors.ambiguous_outcome');
        } catch (FattureInCloudException $exception) {
            $this->submissionStatus = 'error';
            $this->submissionMessage = $exception->getMessage();
        } catch (Throwable $exception) {
            Log::error('Fatture in Cloud quote submission failed unexpectedly.', [
                'exception' => $exception::class,
            ]);
            $this->submissionStatus = 'error';
            $this->submissionMessage = __('assestme.fatture_in_cloud.composer.creation_failed');
        } finally {
            $this->submissionInProgress = false;
        }
    }

    public function selectProduct(int $rowIndex, string $productId): void
    {
        $companyId = app(FattureInCloudSettings::class)->company_id;
        if (! isset($this->rows[$rowIndex]) || ! is_string($companyId)) {
            return;
        }
        if ($productId === '') {
            $this->rows[$rowIndex]['product_id'] = null;
            $this->rows[$rowIndex]['product_code'] = null;

            return;
        }
        try {
            $product = app(FattureInCloudApi::class)->product($companyId, $productId);
        } catch (Throwable) {
            $product = null;
        }
        if (! $product instanceof FattureInCloudProductData) {
            Notification::make()->danger()->title(__('assestme.fatture_in_cloud.composer.product_invalid'))->send();

            return;
        }
        $this->rows[$rowIndex]['product_id'] = $product->id;
        $this->rows[$rowIndex]['product_code'] = $product->code;
        $this->rows[$rowIndex]['title'] = $product->name;
        $this->rows[$rowIndex]['description'] = $product->description ?? $this->rows[$rowIndex]['description'];
        $this->rows[$rowIndex]['measure'] = $product->measure ?? $this->rows[$rowIndex]['measure'];
        $this->rows[$rowIndex]['net_price'] = $product->netPrice === null ? $this->rows[$rowIndex]['net_price'] : (string) $product->netPrice;
        $this->rows[$rowIndex]['vat_type_id'] = $product->vatTypeId ?? $this->rows[$rowIndex]['vat_type_id'];
    }

    public function assessment(): Assessment
    {
        $record = $this->getRecord();
        if (! $record instanceof Assessment) {
            throw new \LogicException('The quote composer record must be an assessment.');
        }

        return $record;
    }

    private function ready(): bool
    {
        $settings = app(FattureInCloudSettings::class);

        return is_string($settings->company_id) && $settings->company_id !== ''
            && is_string($settings->encrypted_refresh_token) && $settings->encrypted_refresh_token !== ''
            && is_string($settings->default_vat_type_id) && $settings->default_vat_type_id !== ''
            && $settings->scope_version === FattureInCloudConfiguration::SCOPE_VERSION;
    }

    private function matchesLocalFiscalIdentity(FattureInCloudClientData $remote): bool
    {
        $local = $this->assessment()->client;

        return (FattureInCloudFiscalIdentifier::vat($local->vat_number) !== null
                && FattureInCloudFiscalIdentifier::vat($local->vat_number) === FattureInCloudFiscalIdentifier::vat($remote->vatNumber))
            || (FattureInCloudFiscalIdentifier::taxCode($local->tax_code) !== null
                && FattureInCloudFiscalIdentifier::taxCode($local->tax_code) === FattureInCloudFiscalIdentifier::taxCode($remote->taxCode));
    }

    private function loadFindings(): void
    {
        $this->findings = $this->assessment()->findings()->with('solutions')->get()->map(
            static fn (Finding $finding): array => [
                'id' => $finding->getKey(),
                'reference' => FattureInCloudQuoteLogic::findingReference($finding->getKey()),
                'title' => $finding->title ?? '',
                'problem' => $finding->problem ?? '',
                'solutions' => $finding->solutions->map(static fn (FindingSolution $solution): array => [
                    'id' => $solution->getKey(),
                    'title' => $solution->title,
                    'description' => $solution->description,
                    'estimate' => $solution->formattedEstimate(),
                ])->all(),
            ],
        )->all();
    }

    private function loadVatTypes(): void
    {
        $companyId = app(FattureInCloudSettings::class)->company_id;
        if (! is_string($companyId)) {
            return;
        }
        $this->vatLoadFailed = false;
        try {
            $this->vatTypes = collect(app(FattureInCloudApi::class)->vatTypes($companyId))
                ->reject(static fn (FattureInCloudVatTypeData $vat): bool => $vat->disabled)
                ->map(static fn (FattureInCloudVatTypeData $vat): array => [
                    'id' => $vat->id,
                    'label' => $vat->label(),
                ])->values()->all();
        } catch (Throwable) {
            $this->vatTypes = [];
            $this->vatLoadFailed = true;
        }
    }

    /** @return array{id: string, kind: string, title: string, description: string, net_price: string, quantity: string, measure: string, discount: string, vat_type_id: string, product_id: null, product_code: null, finding_ids: list<int>, solution_ids: list<int>, unmatched: false} */
    private function emptyRow(string $kind): array
    {
        return [
            'id' => (string) Str::uuid(),
            'kind' => $kind,
            'title' => '',
            'description' => '',
            'net_price' => '',
            'quantity' => '1',
            'measure' => '',
            'discount' => '0',
            'vat_type_id' => (string) app(FattureInCloudSettings::class)->default_vat_type_id,
            'product_id' => null,
            'product_code' => null,
            'finding_ids' => [],
            'solution_ids' => [],
            'unmatched' => false,
        ];
    }

    private function prefillRowFromFindings(int $rowIndex): void
    {
        $findingIds = $this->rows[$rowIndex]['finding_ids'];
        $baseDescription = FattureInCloudQuoteLogic::descriptionWithoutReferences(
            $this->rows[$rowIndex]['description'],
        );

        if (count($findingIds) === 1) {
            $finding = collect($this->findings)->firstWhere('id', $findingIds[0]);
            if (is_array($finding)) {
                $this->rows[$rowIndex]['title'] = $finding['title'];
                $this->rows[$rowIndex]['description'] = FattureInCloudQuoteLogic::descriptionWithReferences(
                    $finding['problem'],
                    $findingIds,
                );
            }

            return;
        }

        $this->rows[$rowIndex]['title'] = '';
        if (collect($this->findings)->contains(
            static fn (array $finding): bool => $finding['problem'] === $baseDescription,
        )) {
            $baseDescription = '';
        }
        $this->rows[$rowIndex]['description'] = FattureInCloudQuoteLogic::descriptionWithReferences(
            $baseDescription,
            $findingIds,
        );
    }

    private function priceSuggestionUnavailable(): void
    {
        Notification::make()->warning()
            ->title(__('assestme.fatture_in_cloud.composer.price_suggestion_unavailable'))
            ->send();
    }

    private function compatibleRowPrice(int $rowIndex): ?float
    {
        if (! isset($this->rows[$rowIndex])) {
            return null;
        }

        $solutionIds = array_values(array_unique($this->rows[$rowIndex]['solution_ids']));
        if ($solutionIds === []) {
            return null;
        }
        $findingIds = array_values(array_unique($this->rows[$rowIndex]['finding_ids']));
        $solutions = FindingSolution::query()
            ->whereIn('id', $solutionIds)
            ->whereIn('finding_id', $findingIds)
            ->get();
        if ($solutions->count() !== count($solutionIds)) {
            return null;
        }

        return FattureInCloudQuoteLogic::compatibleExactSum($solutions->all());
    }

    private static function decimal(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
