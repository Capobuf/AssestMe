<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

it('defines a production-only self-contained shared-hosting archive build', function (): void {
    $projectPath = dirname(__DIR__, 2);
    $script = file_get_contents($projectPath.'/scripts/build-release.sh');
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
        $projectPath.'/scripts/build-release.sh',
        '../unsafe',
        sys_get_temp_dir().'/assestme-invalid-release',
    ]);
    $process->run();

    expect($process->getExitCode())->toBe(64)
        ->and($process->getErrorOutput())->toContain('Usage:');
});

it('defines one fail-closed extracted-release browser acceptance path', function (): void {
    $projectPath = dirname(__DIR__, 2);
    $script = file_get_contents($projectPath.'/scripts/release-installer-acceptance.sh');
    $browserTest = file_get_contents($projectPath.'/tests/Browser/ReleaseInstallationTest.php');

    expect($script)
        ->toContain('php artisan dusk --without-tty tests/Browser/ReleaseInstallationTest.php')
        ->toContain('ASSESTME_DUSK_INSTALLER=1')
        ->toContain('ASSESTME_DUSK_APPLICATION_URL="$configured_application_url"')
        ->toContain('storage/logs/release-server.log')
        ->toContain('storage/app/private/installed.lock')
        ->toContain('php artisan schedule:run')
        ->toContain('php artisan assestme:diagnose --json')
        ->toContain('${application_url}/up')
        ->toContain('${application_url}/admin/login')
        ->toContain('if [[ "$server_status" -eq 139 ]]')
        ->toContain('exit 139')
        ->not->toContain('strace')
        ->not->toContain('KNOWN_GOOD_COMMIT')
        ->not->toContain('continue-on-error');

    expect($browserTest)
        ->toContain('final class ReleaseInstallationTest')
        ->toContain('[data-dusk="installation-complete"]')
        ->toContain('Installer finalization lost the release HTTP server.');
    expect(str_contains($browserTest, "waitForText('AssestMe è pronto'"))->toBeFalse();
});

it('rejects release installer acceptance without an extracted release', function (): void {
    $projectPath = dirname(__DIR__, 2);
    $process = new Process([
        $projectPath.'/scripts/release-installer-acceptance.sh',
        $projectPath.'/missing-release',
    ], $projectPath);
    $process->run();

    expect($process->getExitCode())->toBe(1)
        ->and($process->getErrorOutput())->toContain('Usage:');
});
