<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

it('defines a production-only self-contained CloudPanel archive build', function (): void {
    $projectPath = dirname(__DIR__, 2);
    $script = file_get_contents($projectPath.'/scripts/build-cloudpanel-release.sh');
    $legacyArchiveSuffix = '-cloudpanel'.'.zip';
    $externalChecksumSuffix = '.zip'.'.sha256';

    expect($script)
        ->toContain('composer install')
        ->toContain('--no-dev')
        ->toContain('--classmap-authoritative')
        ->toContain('php artisan filament:assets')
        ->toContain("--exclude='.env'")
        ->toContain("--exclude='vendor/'")
        ->toContain('RELEASE-MANIFEST.sha256')
        ->toContain('vendor/autoload.php')
        ->toContain('deploy/shared-hosting/index.php.dist')
        ->toContain('assestme-${release_version}.zip')
        ->not->toContain($legacyArchiveSuffix)
        ->not->toContain($externalChecksumSuffix)
        ->not->toContain('checksum'.'_path');
});

it('rejects an unsafe release version before creating files', function (): void {
    $projectPath = dirname(__DIR__, 2);
    $process = new Process([
        $projectPath.'/scripts/build-cloudpanel-release.sh',
        '../unsafe',
        sys_get_temp_dir().'/assestme-invalid-release',
    ]);
    $process->run();

    expect($process->getExitCode())->toBe(64)
        ->and($process->getErrorOutput())->toContain('Usage:');
});

it('installs the extracted release with production-safe configuration checks', function (): void {
    $projectPath = dirname(__DIR__, 2);
    $script = file_get_contents($projectPath.'/scripts/ci-install-release-sqlite.sh');

    expect($script)
        ->toContain('configured_url="https://127.0.0.1:${port}"')
        ->toContain('--data-urlencode "application_url=$configured_url"')
        ->toContain('exec env')
        ->toContain('artisan serve --no-reload')
        ->toContain('RELEASE_HTTP_SERVER_PID_STATUS')
        ->toContain('RELEASE_HTTP_SERVER_EXIT_CODE')
        ->toContain('RELEASE_HTTP_LAST_FINALIZE_MARKER')
        ->toContain('pgrep -P "$server_pid"')
        ->toContain('-u APP_ENV')
        ->toContain('-u DB_DATABASE')
        ->toContain('-u ASSESTME_BACKUP_ROOT')
        ->toContain('artisan assestme:diagnose --json')
        ->toContain('echo App\\Models\\User::query()->count();');
});

it('runs equivalent HTTP and browser installer paths with fail-closed server diagnostics', function (): void {
    $projectPath = dirname(__DIR__, 2);
    $workflow = file_get_contents($projectPath.'/.github/workflows/quality.yml');
    $browserTest = file_get_contents($projectPath.'/tests/Browser/CloudPanelInstallationTest.php');

    expect($workflow)
        ->toContain('release-http')
        ->toContain('release-dusk')
        ->toContain('RELEASE_HTTP_SMOKE_RESULT')
        ->toContain('RELEASE_DUSK_RESULT')
        ->toContain('RELEASE_SERVER_PID_STATUS')
        ->toContain('RELEASE_SERVER_EXIT_CODE')
        ->toContain('RELEASE_SERVER_PORT_8123_LISTEN')
        ->toContain('LAST_FINALIZE_MARKER');
    expect($browserTest)
        ->toContain('[data-dusk="installation-complete"]')
        ->toContain('Installer finalization lost the release HTTP server.');
    expect(str_contains($browserTest, "waitForText('AssestMe è pronto'"))->toBeFalse();
});
