<?php

declare(strict_types=1);

use App\Actions\Reports\GeneratePdfRendererSpike;
use App\Models\GeneratedReport;
use App\Models\User;
use App\Services\Reporting\DomPdfCanvasDriver;
use App\Support\Reporting\PdfRendererSpikeReportFactory;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Drivers\ChromeDriver;
use Spatie\LaravelPdf\Drivers\PdfDriver;
use Spatie\LaravelPdf\PdfOptions;
use Symfony\Component\Process\Process;

function runPdfSpikeProcess(array $command): string
{
    $process = new Process($command, base_path());
    $process->setTimeout(30);
    $process->run();

    expect($process->isSuccessful())
        ->toBeTrue($process->getErrorOutput());

    return $process->getOutput();
}

function pdfSpikePageText(string $path, int $page): string
{
    return runPdfSpikeProcess([
        '/usr/bin/pdftotext',
        '-f',
        (string) $page,
        '-l',
        (string) $page,
        '-layout',
        $path,
        '-',
    ]);
}

function pdfSpikePageSize(string $path, int $page): string
{
    $info = runPdfSpikeProcess([
        '/usr/bin/pdfinfo',
        '-f',
        (string) $page,
        '-l',
        (string) $page,
        $path,
    ]);

    preg_match('/^Page\s+\d+\s+size:\s+(.+)$/m', $info, $matches);

    return trim($matches[1] ?? '');
}

it('generates the selected renderer proof without changing production persistence', function (): void {
    $report = app(PdfRendererSpikeReportFactory::class)->make();
    $snapshotBefore = $report->toArray();
    $reportCountBefore = GeneratedReport::query()->count();
    $immutableFilesBefore = Storage::disk('local')->allFiles('reports');

    $result = app(GeneratePdfRendererSpike::class)($report);
    $contents = file_get_contents($result->path);
    $metrics = json_decode(
        (string) file_get_contents(dirname($result->path).'/metrics.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($contents)->toBeString()
        ->and(substr($contents, 0, 5))->toBe('%PDF-')
        ->and($result->sizeBytes)->toBe(strlen($contents))
        ->and($result->sha256)->toBe(hash('sha256', $contents))
        ->and($metrics['renderer'])->toBe('weasyprint')
        ->and($metrics['path'])->toBe($result->path)
        ->and($metrics['size_bytes'])->toBe($result->sizeBytes)
        ->and($metrics['sha256'])->toBe($result->sha256)
        ->and($report->toArray())->toBe($snapshotBefore)
        ->and(GeneratedReport::query()->count())->toBe($reportCountBefore)
        ->and(Storage::disk('local')->allFiles('reports'))->toBe($immutableFilesBefore)
        ->and(app(PdfDriver::class))->toBeInstanceOf(DomPdfCanvasDriver::class)
        ->and(config('laravel-pdf.driver'))->toBe('dompdf');
});

it('meets the D-059 page text orientation pagination footer and raster criteria', function (): void {
    $report = app(PdfRendererSpikeReportFactory::class)->make();
    $result = app(GeneratePdfRendererSpike::class)($report);
    $info = runPdfSpikeProcess(['/usr/bin/pdfinfo', $result->path]);
    $allText = runPdfSpikeProcess(['/usr/bin/pdftotext', '-layout', $result->path, '-']);
    $normalizedText = (string) preg_replace('/\s+/u', ' ', $allText);

    expect($info)->toContain('Producer:        WeasyPrint 57.2', 'Pages:           8')
        ->and($normalizedText)->toContain(
            'Assessment sicurezza',
            'Quadro generale',
            'Riepilogo dei finding',
            'Rete piatta fra uffici',
            'FINDING 2 — CONTINUAZIONE',
            'Immagine di evidenza verificata',
            'Centrale antincendio',
            'Sede operativa di',
            'Montebelluna',
            'Notifier AM-8200N',
            '192.168.10.47',
        );

    foreach ($report->findings as $finding) {
        expect($normalizedText)->toContain($finding->title);
        foreach ($finding->solutions as $solution) {
            expect(substr_count($normalizedText, "SOL-{$solution->id}"))->toBe(1);
        }
    }

    foreach (range(1, 8) as $page) {
        $size = pdfSpikePageSize($result->path, $page);
        if ($page === 3) {
            expect($size)->toMatch('/841\.\d+ x 595\.\d+ pts \(A4\)/');
        } else {
            expect($size)->toMatch('/595\.\d+ x 841\.\d+ pts \(A4\)/');
        }
    }

    $pageTexts = [];
    foreach (range(1, 8) as $page) {
        $pageTexts[$page] = pdfSpikePageText($result->path, $page);
    }

    $pageFive = (string) preg_replace('/\s+/u', ' ', $pageTexts[5]);
    $pageSix = (string) preg_replace('/\s+/u', ' ', $pageTexts[6]);

    expect(preg_match('/^[ \t]*\d+[ \t]*$/m', $pageTexts[1]))->toBe(0)
        ->and(preg_match('/^[ \t]*1[ \t]*$/m', $pageTexts[2]))->toBe(1)
        ->and($pageFive)->toContain('SOL-201', 'Backup immutabile fuori dominio')
        ->and($pageFive)->not->toContain('SOL-202', 'SOL-203')
        ->and($pageSix)->toContain(
            'FINDING 2 — CONTINUAZIONE',
            'SOL-202',
            'SOL-203',
            'Repository offline a rotazione',
            'Servizio gestito di backup',
        )
        ->and($pageSix)->not->toContain('SOL-201', 'Backup immutabile fuori dominio')
        ->and(substr_count($normalizedText, __('pdf_renderer_spike.fixed_note')))->toBe(1);

    $images = runPdfSpikeProcess(['/usr/bin/pdfimages', '-list', $result->path]);
    preg_match_all('/^\s*\d+\s+\d+\s+image\s+960\s+720\s+/m', $images, $matches);
    expect($matches[0])->toHaveCount(1);
});

it('exposes the shared preview only to authenticated local or testing users', function (): void {
    $this->get(route('qa.pdf-renderer-spike.preview'))
        ->assertRedirect('/admin/login');

    $this->actingAs(User::factory()->create())
        ->get(route('qa.pdf-renderer-spike.preview'))
        ->assertOk()
        ->assertSee('data-proof-section="cover"', false)
        ->assertSee('data-proof-section="summary"', false)
        ->assertSee('@media screen', false)
        ->assertSee('@media print', false)
        ->assertSee('width: 297mm', false);

    $process = new Process(
        ['php', 'artisan', 'route:list', '--name=qa.pdf-renderer-spike', '--env=production'],
        base_path(),
        ['APP_ENV' => 'production'],
    );
    $process->setTimeout(30);
    $process->run();

    expect($process->isSuccessful())
        ->toBeTrue($process->getErrorOutput())
        ->and($process->getOutput().$process->getErrorOutput())
        ->not->toContain('admin/qa/pdf-renderer-spike');
});

it('records the official Chrome driver CSS page size blocker', function (): void {
    $version = runPdfSpikeProcess(['/usr/bin/chromium', '--version']);
    preg_match('/Chromium\s+(\d+)/', $version, $matches);

    expect((int) ($matches[1] ?? 0))->toBeGreaterThanOrEqual(131);

    $driver = app('laravel-pdf.driver.chrome');
    expect($driver)->toBeInstanceOf(ChromeDriver::class);

    $method = new ReflectionMethod(ChromeDriver::class, 'buildPdfOptions');
    $options = $method->invoke($driver, null, null, new PdfOptions);

    expect($options)
        ->toBeArray()
        ->toHaveKey('printBackground', true)
        ->not->toHaveKey('preferCSSPageSize');
});
