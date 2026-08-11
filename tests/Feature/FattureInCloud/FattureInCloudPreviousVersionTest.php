<?php

declare(strict_types=1);

use App\Actions\FattureInCloud\FattureInCloudQuoteLogic;
use App\Actions\FattureInCloud\FindPreviousFattureInCloudQuote;
use App\Models\Assessment;
use App\Models\Finding;
use App\Services\FattureInCloud\FattureInCloudAmbiguousOutcome;
use App\Settings\FattureInCloudSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

it('finds the greatest exact current-assessment quote across all pages', function (): void {
    ficPreviousReadySettings();
    $assessment = Assessment::factory()->create();
    $marker = static fn (int $version): string => FattureInCloudQuoteLogic::marker($assessment->getKey(), $version);
    Http::fake(function (Request $request) use ($assessment, $marker) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        expect($query['q'] ?? null)->toBe("any_subject contains '[ASSESTME assessment={$assessment->getKey()}'");
        $page = (int) ($query['page'] ?? 1);

        return Http::response([
            'data' => $page === 1 ? [
                ficPreviousDocument(10, $marker(2)),
                ficPreviousDocument(11, 'X '.$marker(9)),
                ficPreviousDocument(12, '[assestme assessment='.$assessment->getKey().' version=8]'),
            ] : [
                ficPreviousDocument(13, $marker(7)),
                ficPreviousDocument(14, FattureInCloudQuoteLogic::marker($assessment->getKey() + 1, 20)),
                ficPreviousDocument(15, $marker(30), 'invoice'),
            ],
            'meta' => ['pagination' => ['last_page' => 2]],
        ], 200);
    });

    $latest = app(FindPreviousFattureInCloudQuote::class)->latest($assessment);

    expect($latest?->id)->toBe('13')
        ->and($latest?->subject)->toBe($marker(7));
    Http::assertSentCount(2);
});

it('rejects duplicate documents at the greatest exact version', function (): void {
    ficPreviousReadySettings();
    $assessment = Assessment::factory()->create();
    $marker = FattureInCloudQuoteLogic::marker($assessment->getKey(), 7);
    Http::fake([
        '*/issued_documents*' => Http::response([
            'data' => [
                ficPreviousDocument(13, $marker),
                ficPreviousDocument(14, $marker),
            ],
            'meta' => ['pagination' => ['last_page' => 1]],
        ], 200),
    ]);

    expect(fn () => app(FindPreviousFattureInCloudQuote::class)->latest($assessment))
        ->toThrow(FattureInCloudAmbiguousOutcome::class, __('assestme.fatture_in_cloud.composer.previous_ambiguous'));
});

it('reconstructs remote rows and links only exact current-assessment references', function (): void {
    ficPreviousReadySettings();
    $assessment = Assessment::factory()->create();
    $first = Finding::factory()->create(['assessment_id' => $assessment->getKey()]);
    $second = Finding::factory()->create(['assessment_id' => $assessment->getKey()]);
    $validReference = FattureInCloudQuoteLogic::findingReference($first->getKey());
    $otherReference = FattureInCloudQuoteLogic::findingReference($second->getKey());
    Http::fake([
        '*/issued_documents/77*' => Http::response(['data' => ficPreviousDocument(
            77,
            FattureInCloudQuoteLogic::marker($assessment->getKey(), 4),
            'quote',
            [[
                'product_id' => 9,
                'code' => 'SERV-9',
                'name' => 'Riga precedente',
                'description' => "Testo\n\nRiferimenti AssestMe: {$validReference}, F-999999, {$otherReference}0",
                'qty' => 2,
                'measure' => 'ore',
                'net_price' => 75,
                'discount' => 5,
                'vat' => ['id' => 22],
            ]],
        )], 200),
    ]);

    $rows = app(FindPreviousFattureInCloudQuote::class)->load($assessment, '77');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['finding_ids'])->toBe([$first->getKey()])
        ->and($rows[0]['unmatched'])->toBeFalse()
        ->and($rows[0]['product_id'])->toBe('9')
        ->and($rows[0]['product_code'])->toBe('SERV-9')
        ->and($rows[0]['net_price'])->toBe('75');
});

it('preserves an unmatched prior row without guessing a Finding', function (): void {
    ficPreviousReadySettings();
    $assessment = Assessment::factory()->create();
    Http::fake([
        '*/issued_documents/88*' => Http::response(['data' => ficPreviousDocument(
            88,
            FattureInCloudQuoteLogic::marker($assessment->getKey(), 1),
            'quote',
            [[
                'name' => 'Riga orfana', 'description' => 'Riferimento F-999999',
                'qty' => 1, 'net_price' => 10, 'discount' => 0, 'vat' => ['id' => 22],
            ]],
        )], 200),
    ]);

    $rows = app(FindPreviousFattureInCloudQuote::class)->load($assessment, '88');

    expect($rows[0]['finding_ids'])->toBe([])
        ->and($rows[0]['unmatched'])->toBeTrue()
        ->and($rows[0]['title'])->toBe('Riga orfana');
});

function ficPreviousReadySettings(): void
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
}

/** @param list<array<string, mixed>> $items */
function ficPreviousDocument(int $id, string $subject, string $type = 'quote', array $items = []): array
{
    return [
        'id' => $id,
        'type' => $type,
        'subject' => $subject,
        'visible_subject' => 'Preventivo precedente',
        'url' => 'https://example.test/quote/'.$id,
        'items_list' => $items,
    ];
}
