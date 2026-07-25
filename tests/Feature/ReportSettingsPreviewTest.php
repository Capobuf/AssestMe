<?php

declare(strict_types=1);

use App\Filament\Pages\ReportSettingsPage;
use App\Models\GeneratedReport;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('serves the shared report view from an authenticated session-bound preview token without side effects', function (): void {
    Storage::fake('local');
    $user = User::factory()->create();
    $this->actingAs($user)->withSession(['report_preview_binding' => 'feature-preview-session']);
    $filesBefore = Storage::disk('local')->allFiles('reports');

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
        ->assertSeeHtml('class="assestme-report-preview__frame"');
    $url = html_entity_decode($previewMatch[1], ENT_QUOTES | ENT_HTML5);
    $token = basename((string) parse_url($url, PHP_URL_PATH));
    Cache::put(sprintf(
        'report-preview:%d:%s:%s',
        $user->getKey(),
        hash('sha256', 'feature-preview-session'),
        $token,
    ), [
        'default_title_pattern' => 'Anteprima non salvata — {client}',
        'primary_color' => '#B42318',
        'executive_summary' => true,
        'summary_table' => true,
    ], now()->addMinutes(5));

    $this->get($url)
        ->assertOk()
        ->assertSee('Anteprima non salvata')
        ->assertSee('Quadro generale')
        ->assertSee('Riepilogo dei finding')
        ->assertSee('Ripristino dei backup non verificato')
        ->assertSee('Servizio RDP esposto direttamente su Internet')
        ->assertSee('Notifiche del NAS non configurate')
        ->assertSee('Da 1.800 a 2.600 € una tantum')
        ->assertSee('390 € all’anno')
        ->assertSee('29 € al mese per utente')
        ->assertSee('Richiede analisi')
        ->assertSee('Richiede preventivo')
        ->assertSee('Compreso in altra attività')
        ->assertSee('data-finding-page="1"', false)
        ->assertSee('@page report', false)
        ->assertSee('size: A4 portrait', false)
        ->assertSee('@page summary', false)
        ->assertSee('size: A4 landscape', false)
        ->assertSee('--accent: #B42318', false)
        ->assertSee('class="assestme-report-preview-document"', false)
        ->assertSee('body.dataset.reportPreviewFitted', false);

    expect(GeneratedReport::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('reports'))->toBe($filesBefore);

    $this->actingAs($user)
        ->withSession(['report_preview_binding' => 'another-preview-session'])
        ->get($url)
        ->assertNotFound();
});

it('requires authentication and rejects missing preview state', function (): void {
    $this->get(route('report-settings.preview', ['token' => str_repeat('a', 40)]))
        ->assertRedirect('/admin/login');

    $this->actingAs(User::factory()->create())
        ->get(route('report-settings.preview', ['token' => str_repeat('a', 40)]))
        ->assertNotFound();
});
