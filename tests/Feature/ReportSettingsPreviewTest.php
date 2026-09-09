<?php

declare(strict_types=1);

use App\Actions\Reports\GenerateAssessmentPdf;
use App\Filament\Pages\ReportSettingsPage;
use App\Http\Controllers\ReportSettingsPreviewController;
use App\Models\GeneratedReport;
use App\Models\User;
use App\Services\Reporting\WeasyPrintReportRenderer;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Smalot\PdfParser\Parser;

it('uses the same explicit WeasyPrint renderer service for production and preview', function (): void {
    $productionRenderer = (new ReflectionClass(GenerateAssessmentPdf::class))
        ->getConstructor()
        ?->getParameters()[1]
        ->getType();
    $previewRenderer = (new ReflectionMethod(ReportSettingsPreviewController::class, '__invoke'))
        ->getParameters()[3]
        ->getType();

    expect($productionRenderer?->getName())->toBe(WeasyPrintReportRenderer::class)
        ->and($previewRenderer?->getName())->toBe(WeasyPrintReportRenderer::class)
        ->and(config('laravel-pdf.driver'))->toBe('weasyprint')
        ->and(class_exists(Dompdf::class))->toBeFalse();
});

it('serves the shared report view from an authenticated session-bound preview token without side effects', function (): void {
    Storage::fake('local');
    $user = User::factory()->create();
    $this->actingAs($user)->withSession(['report_preview_binding' => 'feature-preview-session']);
    $filesBefore = Storage::disk('local')->allFiles('reports');
    Storage::disk('local')->put(
        'branding/preview-logo.png',
        (string) file_get_contents(base_path('fixtures/evidence/valid-small.png')),
    );

    $page = Livewire::test(ReportSettingsPage::class)
        ->fillForm([
            'default_title_pattern' => 'Anteprima non salvata — {client}',
            'primary_color' => '#B42318',
            'executive_summary' => true,
            'summary_table' => true,
        ]);
    preg_match('/<iframe[^>]+src="([^"]+)"/u', $page->html(), $previewMatch);
    expect($previewMatch)->toHaveKey(1);
    $page->assertSee(__('assestme.settings.preview.heading'))
        ->assertDontSee('Anteprima stile')
        ->assertDontSee('Anteprima del report reale con lo stesso layout usato dal PDF.')
        ->assertSeeHtml('class="assestme-report-preview__heading"')
        ->assertSeeHtml('class="assestme-report-preview__frame"')
        ->assertSee(__('assestme.settings.preview.expand'))
        ->assertDontSee(__('assestme.settings.fields.summary_solutions'))
        ->assertDontSee(__('assestme.settings.fields.technical_notes_in_report'))
        ->assertDontSee(__('assestme.settings.fields.costs_in_report'))
        ->assertDontSee(__('assestme.settings.fields.new_page_per_finding'))
        ->assertSee(__('assestme.settings.help.default_title_pattern'))
        ->assertSee(__('assestme.settings.help.freeze_after_generation'))
        ->assertSee(__('assestme.settings.help.report_excluded_findings_in_xlsx'));
    $url = html_entity_decode($previewMatch[1], ENT_QUOTES | ENT_HTML5);
    $token = basename((string) parse_url($url, PHP_URL_PATH));
    $cacheKey = sprintf(
        'report-preview:%d:%s:%s',
        $user->getKey(),
        hash('sha256', 'feature-preview-session'),
        $token,
    );
    $forwardedSettings = Cache::get($cacheKey);
    expect($forwardedSettings)->toBeArray()->toHaveKeys([
        'default_title_pattern',
        'consultant_name',
        'business_name',
        'consultant_role',
        'consultant_email',
        'consultant_phone',
        'consultant_website',
        'consultant_address',
        'consultant_vat_number',
        'consultant_pec',
        'consultant_tax_code',
        'consultant_logo_path',
        'signature_name',
        'signature_role',
        'primary_color',
        'branding',
        'cover_title_mode',
        'show_priority_descriptions',
        'cover',
        'content_index',
        'executive_summary',
        'risk_legend',
        'summary_table',
        'methodology',
        'page_numbers',
        'show_resolution',
        'signature_block',
        'disclaimer',
        'technical_notes',
        'alternative_solutions',
        'costs',
        'evidence',
        'evidence_captions',
        'methodology_text',
        'disclaimer_text',
        'signature_text',
        'currency',
        'currency_symbol',
        'currency_symbol_position',
        'currency_decimals',
    ])->not->toHaveKeys([
        'summary_solutions',
        'technical_notes_in_report',
        'costs_in_report',
        'new_page_per_finding',
        'freeze_after_generation',
        'report_excluded_findings_in_xlsx',
    ]);

    Cache::put($cacheKey, [
        'default_title_pattern' => 'Anteprima non salvata — {client}',
        'primary_color' => '#B42318',
        'branding' => 'consultant',
        'cover_title_mode' => 'combined',
        'consultant_name' => 'Mario Anteprima',
        'business_name' => 'Studio Anteprima S.r.l.',
        'consultant_role' => 'Consulente IT',
        'consultant_email' => 'anteprima@example.test',
        'consultant_phone' => '+39 0123456789',
        'consultant_website' => 'https://example.test',
        'consultant_address' => "Via Test 1\nTreviso",
        'consultant_vat_number' => 'IT01234567890',
        'consultant_pec' => 'anteprima@pec.example.test',
        'consultant_tax_code' => 'RSSMRA80A01H501U',
        'consultant_logo_path' => 'branding/preview-logo.png',
        'signature_name' => 'Mario Anteprima',
        'signature_role' => 'Consulente IT',
        'show_priority_descriptions' => true,
        'cover' => true,
        'content_index' => true,
        'executive_summary' => true,
        'risk_legend' => true,
        'summary_table' => true,
        'methodology' => true,
        'page_numbers' => true,
        'show_resolution' => true,
        'signature_block' => true,
        'disclaimer' => true,
        'technical_notes' => true,
        'alternative_solutions' => true,
        'costs' => true,
        'evidence' => true,
        'evidence_captions' => true,
        'methodology_text' => 'Metodo configurato in anteprima.',
        'disclaimer_text' => 'Disclaimer configurato in anteprima.',
        'signature_text' => 'Testo firma configurato in anteprima.',
        'currency' => 'EUR',
        'currency_symbol' => 'CHF',
        'currency_symbol_position' => 'before',
        'currency_decimals' => 2,
    ], now()->addMinutes(5));

    $response = $this->get($url)
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('cache-control', 'no-store, private')
        ->assertHeader('x-content-type-options', 'nosniff');
    $contents = (string) $response->getContent();
    $text = (new Parser)->parseContent($contents)->getText();
    $normalizedText = trim((string) preg_replace('/\s+/u', ' ', $text));

    expect($contents)->toStartWith('%PDF-')
        ->and($normalizedText)->toContain(
            'Anteprima non salvata — Azienda Demo S.r.l.',
            'Quadro Generale',
            'Riepilogo dei Finding',
            'Ripristino dei backup non verificato',
            'Servizio RDP esposto direttamente su Internet',
            'Notifiche del NAS non configurate',
            'CHF 2.600 una tantum',
            'CHF 390/anno',
            'CHF 29/al mese per utente',
            'Richiede approfondimento',
            'Richiede preventivo',
            'Da sommare ad altre attività',
            'Mario Anteprima',
            'Studio Anteprima S.r.l.',
            'anteprima@example.test',
            'Metodo configurato in anteprima.',
            'Disclaimer configurato in anteprima.',
            'Testo firma configurato in anteprima.',
            'Esempio di didascalia configurabile per una evidenza inclusa.',
        );

    expect(GeneratedReport::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('reports'))->toBe($filesBefore);

    $this->actingAs($user)
        ->withSession(['report_preview_binding' => 'another-preview-session'])
        ->get($url)
        ->assertNotFound();
});

it('applies disabled visual settings to the transient WeasyPrint preview', function (): void {
    Storage::fake('local');
    $user = User::factory()->create();
    $this->actingAs($user)->withSession(['report_preview_binding' => 'disabled-preview-session']);
    $token = str_repeat('b', 40);
    Cache::put(sprintf(
        'report-preview:%d:%s:%s',
        $user->getKey(),
        hash('sha256', 'disabled-preview-session'),
        $token,
    ), [
        'default_title_pattern' => 'Anteprima essenziale — {client}',
        'primary_color' => '#65A30D',
        'branding' => 'client',
        'cover_title_mode' => 'separate',
        'cover' => false,
        'content_index' => false,
        'executive_summary' => true,
        'risk_legend' => false,
        'summary_table' => false,
        'methodology' => false,
        'page_numbers' => false,
        'show_resolution' => false,
        'signature_block' => false,
        'disclaimer' => false,
        'technical_notes' => false,
        'alternative_solutions' => false,
        'costs' => false,
        'evidence' => false,
        'evidence_captions' => false,
        'currency' => 'EUR',
        'currency_symbol' => '€',
        'currency_symbol_position' => 'after',
        'currency_decimals' => 2,
    ], now()->addMinutes(5));

    $response = $this->get(route('report-settings.preview', ['token' => $token]))->assertOk();
    $contents = (string) $response->getContent();
    $text = trim((string) preg_replace(
        '/\s+/u',
        ' ',
        (new Parser)->parseContent($contents)->getText(),
    ));

    expect($contents)->toStartWith('%PDF-')
        ->and($text)->toContain(
            'Quadro Generale',
            'Ripristino dei backup non verificato',
            'Servizio RDP esposto direttamente su Internet',
            'Notifiche del NAS non configurate',
        )
        ->and($text)->not->toContain(
            'Anteprima essenziale',
            'Indice dei contenuti',
            'Riepilogo dei Finding',
            'Legenda priorità',
            'Ambiente dedicato di disaster recovery',
            'Gateway gestito con MFA',
            'Integrare il NAS nel monitoraggio centralizzato',
            'Verificare NAT, regole firewall',
            'Canale SMTP configurato',
            'Evidenze',
            'Metodologia',
            'Disclaimer',
            'Firma',
            'Circa 390',
        );

    expect(GeneratedReport::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('reports'))->toBe([]);
});

it('requires authentication and rejects missing preview state', function (): void {
    $this->get(route('report-settings.preview', ['token' => str_repeat('a', 40)]))
        ->assertRedirect('/admin/login');

    $this->actingAs(User::factory()->create())
        ->get(route('report-settings.preview', ['token' => str_repeat('a', 40)]))
        ->assertNotFound();
});
