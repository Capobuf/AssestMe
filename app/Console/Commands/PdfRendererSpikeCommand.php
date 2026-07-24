<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Reports\GeneratePdfRendererSpike;
use App\Support\Reporting\PdfRendererSpikeReportFactory;
use Illuminate\Console\Command;

final class PdfRendererSpikeCommand extends Command
{
    protected $signature = 'assestme:pdf-renderer-spike';

    protected $description = 'Generate the isolated D-059 selected-renderer PDF proof';

    public function handle(
        PdfRendererSpikeReportFactory $factory,
        GeneratePdfRendererSpike $generate,
    ): int {
        if (! app()->environment(['local', 'testing'])) {
            $this->components->error('This command is available only in local and testing environments.');

            return self::FAILURE;
        }

        $result = $generate($factory->make());

        $this->line((string) json_encode([
            'renderer' => 'weasyprint',
            'path' => $result->path,
            'generation_seconds' => round($result->generationSeconds, 4),
            'size_bytes' => $result->sizeBytes,
            'sha256' => $result->sha256,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
