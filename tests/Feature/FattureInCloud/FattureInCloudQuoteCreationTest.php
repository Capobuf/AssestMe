<?php

declare(strict_types=1);

use App\Actions\FattureInCloud\CreateFattureInCloudQuote;
use App\Actions\FattureInCloud\FattureInCloudQuoteLogic;
use App\Filament\Resources\Assessments\Pages\CreateFattureInCloudQuote as QuoteComposerPage;
use App\Models\Assessment;
use App\Models\Finding;
use App\Models\User;
use App\Settings\FattureInCloudSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

it('creates exactly one quote row with the next exact marker and stores no commercial history', function (): void {
    [$assessment, $finding] = ficCreationContext();
    $tablesBefore = Schema::getTableListing();
    Http::fake(function (Request $request) use ($assessment) {
        if (str_contains($request->url(), '/entities/clients/91')) {
            return Http::response(['data' => ficCreationClient()], 200);
        }
        if (str_contains($request->url(), '/info/vat_types')) {
            return Http::response(['data' => [ficCreationVat()]], 200);
        }
        if ($request->method() === 'GET' && str_contains($request->url(), '/issued_documents')) {
            return Http::response(['data' => [ficCreationDocument(
                40,
                FattureInCloudQuoteLogic::marker($assessment->getKey(), 2),
            )], 'meta' => ['pagination' => ['last_page' => 1]]], 200);
        }
        if ($request->method() === 'POST' && str_contains($request->url(), '/issued_documents')) {
            return Http::response(['data' => ficCreationDocument(55, (string) $request['data']['subject'])], 200);
        }

        return Http::response([], 500);
    });

    $document = app(CreateFattureInCloudQuote::class)(
        $assessment,
        '91',
        [ficCreationRow($finding->getKey())],
    );

    expect($document->id)->toBe('55')
        ->and($document->subject)->toBe(FattureInCloudQuoteLogic::marker($assessment->getKey(), 3))
        ->and(Schema::getTableListing())->toBe($tablesBefore);
    Http::assertSent(static fn (Request $request): bool => $request->method() === 'POST'
        && $request['data']['type'] === 'quote'
        && $request['data']['entity']['id'] === 91
        && $request['data']['entity']['name'] === 'Cliente FIC'
        && $request['data']['entity']['vat_number'] === '01234567890'
        && ! isset($request['data']['entity']['tax_code'])
        && count($request['data']['items_list']) === 1
        && $request['data']['items_list'][0]['vat']['id'] === 22
        && str_contains((string) $request['data']['items_list'][0]['description'], FattureInCloudQuoteLogic::findingReference($finding->getKey())));
    Http::assertSentCount(4);
});

it('refreshes once after a definitive unauthorized quote response', function (): void {
    [$assessment, $finding] = ficCreationContext();
    $postCount = 0;
    Http::fake(function (Request $request) use (&$postCount) {
        if (str_contains($request->url(), '/entities/clients/91')) {
            return Http::response(['data' => ficCreationClient()], 200);
        }
        if (str_contains($request->url(), '/info/vat_types')) {
            return Http::response(['data' => [ficCreationVat()]], 200);
        }
        if ($request->method() === 'GET' && str_contains($request->url(), '/issued_documents')) {
            return Http::response(['data' => [], 'meta' => ['pagination' => ['last_page' => 1]]], 200);
        }
        if (str_contains($request->url(), '/oauth/token')) {
            return Http::response([
                'access_token' => 'new-access', 'refresh_token' => 'new-refresh', 'expires_in' => 86400,
            ], 200);
        }
        if ($request->method() === 'POST') {
            $postCount++;

            return $postCount === 1
                ? Http::response([], 401)
                : Http::response(['data' => ficCreationDocument(56, (string) $request['data']['subject'])], 200);
        }

        return Http::response([], 500);
    });

    $document = app(CreateFattureInCloudQuote::class)($assessment, '91', [ficCreationRow($finding->getKey())]);

    expect($document->id)->toBe('56')
        ->and($postCount)->toBe(2)
        ->and(app(FattureInCloudSettings::class)->refresh()->encrypted_refresh_token)->toBe('new-refresh');
});

it('surfaces an ordinary provider rejection or bounded rate limit without success', function (int $status, ?string $retryAfter, string $message): void {
    [$assessment, $finding] = ficCreationContext();
    Http::fake(function (Request $request) use ($status, $retryAfter) {
        if (str_contains($request->url(), '/entities/clients/91')) {
            return Http::response(['data' => ficCreationClient()], 200);
        }
        if (str_contains($request->url(), '/info/vat_types')) {
            return Http::response(['data' => [ficCreationVat()]], 200);
        }
        if ($request->method() === 'GET') {
            return Http::response(['data' => [], 'meta' => ['pagination' => ['last_page' => 1]]], 200);
        }

        return Http::response([], $status, $retryAfter === null ? [] : ['Retry-After' => $retryAfter]);
    });

    expect(fn () => app(CreateFattureInCloudQuote::class)(
        $assessment,
        '91',
        [ficCreationRow($finding->getKey())],
    ))->toThrow(RuntimeException::class, $message);
})->with([
    'provider rejection' => [422, null, 'Fatture in Cloud ha rifiutato i dati inviati.'],
    'provider failure' => [500, null, 'Fatture in Cloud non è disponibile. Riprova senza modificare i dati.'],
    'bounded rate limit' => [429, '120', '120 secondi'],
    'untrusted retry value' => [429, 'arbitrary provider text', 'Fatture in Cloud ha temporaneamente limitato le richieste.'],
]);

it('logs only bounded rejection metadata and never provider messages or field values', function (): void {
    [$assessment, $finding] = ficCreationContext();
    Log::spy();
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/entities/clients/91')) {
            return Http::response(['data' => ficCreationClient()], 200);
        }
        if (str_contains($request->url(), '/info/vat_types')) {
            return Http::response(['data' => [ficCreationVat()]], 200);
        }
        if ($request->method() === 'GET') {
            return Http::response(['data' => [], 'meta' => ['pagination' => ['last_page' => 1]]], 200);
        }

        return Http::response([
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'secret provider detail',
                'validation_result' => [
                    'data.entity.name' => ['value secret-value is invalid'],
                    'unsafe field' => ['ignored'],
                ],
            ],
        ], 422);
    });

    expect(fn () => app(CreateFattureInCloudQuote::class)(
        $assessment,
        '91',
        [ficCreationRow($finding->getKey())],
    ))->toThrow(RuntimeException::class, __('assestme.fatture_in_cloud.errors.request_rejected'));

    Log::shouldHaveReceived('warning')->once()->withArgs(
        static fn (string $message, array $context): bool => $message === 'Fatture in Cloud API request was rejected.'
            && $context === [
                'operation' => 'issued_documents',
                'status' => 422,
                'error_code' => 'VALIDATION_ERROR',
                'validation_fields' => ['data.entity.name'],
            ],
    );
});

it('reconciles a timeout only when one exact remote marker exists', function (): void {
    [$assessment, $finding] = ficCreationContext();
    $quoteGets = 0;
    $quotePosts = 0;
    Http::fake(function (Request $request) use ($assessment, &$quoteGets, &$quotePosts) {
        if (str_contains($request->url(), '/entities/clients/91')) {
            return Http::response(['data' => ficCreationClient()], 200);
        }
        if (str_contains($request->url(), '/info/vat_types')) {
            return Http::response(['data' => [ficCreationVat()]], 200);
        }
        if ($request->method() === 'GET') {
            $quoteGets++;
            $data = $quoteGets === 1 ? [] : [ficCreationDocument(
                90,
                FattureInCloudQuoteLogic::marker($assessment->getKey(), 1),
            )];

            return Http::response(['data' => $data, 'meta' => ['pagination' => ['last_page' => 1]]], 200);
        }
        $quotePosts++;
        throw new ConnectionException('timeout after dispatch');
    });

    $document = app(CreateFattureInCloudQuote::class)($assessment, '91', [ficCreationRow($finding->getKey())]);

    expect($document->id)->toBe('90')
        ->and($quotePosts)->toBe(1)
        ->and($quoteGets)->toBe(2);
});

it('keeps a timeout unresolved for zero or multiple exact markers without retrying the POST', function (int $matches): void {
    [$assessment, $finding] = ficCreationContext();
    $quoteGets = 0;
    $quotePosts = 0;
    Http::fake(function (Request $request) use ($assessment, $matches, &$quoteGets, &$quotePosts) {
        if (str_contains($request->url(), '/entities/clients/91')) {
            return Http::response(['data' => ficCreationClient()], 200);
        }
        if (str_contains($request->url(), '/info/vat_types')) {
            return Http::response(['data' => [ficCreationVat()]], 200);
        }
        if ($request->method() === 'GET') {
            $quoteGets++;
            $data = $quoteGets === 1 ? [] : array_map(
                fn (int $index): array => ficCreationDocument(
                    100 + $index,
                    FattureInCloudQuoteLogic::marker($assessment->getKey(), 1),
                ),
                range(1, $matches),
            );
            if ($matches === 0) {
                $data = [];
            }

            return Http::response(['data' => $data, 'meta' => ['pagination' => ['last_page' => 1]]], 200);
        }
        $quotePosts++;
        throw new ConnectionException('timeout after dispatch');
    });

    expect(fn () => app(CreateFattureInCloudQuote::class)(
        $assessment,
        '91',
        [ficCreationRow($finding->getKey())],
    ))->toThrow(RuntimeException::class, __('assestme.fatture_in_cloud.errors.ambiguous_outcome'));
    expect($quotePosts)->toBe(1);
})->with(['zero matches' => [0], 'multiple matches' => [2]]);

it('shows a real provider success in transient component state', function (): void {
    [$assessment, $finding] = ficCreationContext();
    $this->actingAs(User::factory()->create());
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/entities/clients/91')) {
            return Http::response(['data' => ficCreationClient()], 200);
        }
        if (str_contains($request->url(), '/info/vat_types')) {
            return Http::response(['data' => [ficCreationVat()]], 200);
        }
        if ($request->method() === 'GET') {
            return Http::response(['data' => [], 'meta' => ['pagination' => ['last_page' => 1]]], 200);
        }

        return Http::response(['data' => ficCreationDocument(501, (string) $request['data']['subject'])], 200);
    });
    $row = ['id' => 'row-1', 'solution_ids' => [], 'unmatched' => false] + ficCreationRow($finding->getKey());

    Livewire::test(QuoteComposerPage::class, ['record' => $assessment->getRouteKey()])
        ->set('rows', [$row])
        ->call('submitQuote')
        ->assertSet('submissionStatus', 'success')
        ->assertSet('submittedDocumentId', '501')
        ->assertSet('rows.0.title', 'Intervento');
});

it('retains component input after failure and blocks an already in-flight submission', function (): void {
    [$assessment, $finding] = ficCreationContext();
    $this->actingAs(User::factory()->create());
    $postCount = 0;
    Http::fake(function (Request $request) use (&$postCount) {
        if (str_contains($request->url(), '/entities/clients/91')) {
            return Http::response(['data' => ficCreationClient()], 200);
        }
        if (str_contains($request->url(), '/info/vat_types')) {
            return Http::response(['data' => [ficCreationVat()]], 200);
        }
        if ($request->method() === 'GET') {
            return Http::response(['data' => [], 'meta' => ['pagination' => ['last_page' => 1]]], 200);
        }
        $postCount++;

        return Http::response([], 422);
    });
    $row = ['id' => 'row-1', 'solution_ids' => [], 'unmatched' => false] + ficCreationRow($finding->getKey());
    $component = Livewire::test(QuoteComposerPage::class, ['record' => $assessment->getRouteKey()])
        ->set('rows', [$row])
        ->call('submitQuote')
        ->assertSet('submissionStatus', 'error')
        ->assertSet('rows.0.title', 'Intervento');
    expect($postCount)->toBe(1);

    $component->set('submissionInProgress', true)
        ->call('submitQuote')
        ->assertSet('submissionInProgress', true);
    expect($postCount)->toBe(1);
});

/** @return array{Assessment, Finding} */
function ficCreationContext(): array
{
    $settings = app(FattureInCloudSettings::class);
    $settings->client_id = 'client';
    $settings->encrypted_client_secret = 'secret';
    $settings->encrypted_access_token = 'access';
    $settings->access_token_expires_at = Carbon::now('UTC')->addHour()->toIso8601String();
    $settings->encrypted_refresh_token = 'refresh';
    $settings->company_id = '4321';
    $settings->company_name = 'Studio Demo';
    $settings->default_vat_type_id = '22';
    $settings->default_vat_type_label = '22%';
    $settings->scope_version = 1;
    $settings->save();
    $assessment = Assessment::factory()->create();
    $assessment->client->update([
        'fatture_in_cloud_company_id' => '4321',
        'fatture_in_cloud_client_id' => '91',
    ]);
    $finding = Finding::factory()->create(['assessment_id' => $assessment->getKey()]);

    return [$assessment, $finding];
}

/** @return array<string, mixed> */
function ficCreationRow(int $findingId): array
{
    return [
        'kind' => 'group', 'title' => 'Intervento', 'description' => 'Descrizione',
        'net_price' => '100', 'quantity' => '1', 'measure' => 'ore', 'discount' => '0',
        'vat_type_id' => '22', 'product_id' => null, 'product_code' => null,
        'finding_ids' => [$findingId],
    ];
}

/** @return array{id: int, name: string, vat_number: string, tax_code: null} */
function ficCreationClient(): array
{
    return ['id' => 91, 'name' => 'Cliente FIC', 'vat_number' => '01234567890', 'tax_code' => null];
}

/** @return array{id: int, value: int, description: string, is_disabled: false, default: true} */
function ficCreationVat(): array
{
    return ['id' => 22, 'value' => 22, 'description' => 'Ordinaria', 'is_disabled' => false, 'default' => true];
}

/** @return array<string, mixed> */
function ficCreationDocument(int $id, string $subject): array
{
    return [
        'id' => $id, 'type' => 'quote', 'subject' => $subject,
        'visible_subject' => 'Preventivo', 'url' => 'https://example.test/quotes/'.$id,
        'items_list' => [],
    ];
}
