<?php

declare(strict_types=1);

namespace Tests\Browser;

use Facebook\WebDriver\Exception\TimeoutException;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Assert;
use Tests\DuskTestCase;
use Throwable;

final class ReleaseInstallationTest extends DuskTestCase
{
    public function test_release_installer_closes_permanently_then_allows_login_and_diagnostics(): void
    {
        $fullInstallation = getenv('ASSESTME_DUSK_INSTALLER') === '1';

        $this->browse(function (Browser $browser) use ($fullInstallation): void {
            $browser->visit('/install')->pause(300);

            if (! $fullInstallation) {
                $browser->assertSee('404')
                    ->assertDontSee('Avvia le verifiche');

                return;
            }

            $releasePath = getenv('ASSESTME_RELEASE_PATH');
            $password = getenv('ASSESTME_DUSK_ADMIN_PASSWORD');
            $applicationUrl = getenv('ASSESTME_DUSK_APPLICATION_URL');
            $databaseHost = getenv('ASSESTME_DUSK_DATABASE_HOST');
            $databasePort = getenv('ASSESTME_DUSK_DATABASE_PORT');
            $databaseName = getenv('ASSESTME_DUSK_DATABASE_NAME');
            $databaseUsername = getenv('ASSESTME_DUSK_DATABASE_USERNAME');
            $databasePassword = getenv('ASSESTME_DUSK_DATABASE_PASSWORD');
            Assert::assertIsString($releasePath);
            Assert::assertNotSame('', $releasePath);
            Assert::assertIsString($password);
            Assert::assertNotSame('', $password);
            Assert::assertIsString($applicationUrl);
            Assert::assertMatchesRegularExpression('/^https:\/\/[A-Za-z0-9.-]+(?::[0-9]+)?$/', $applicationUrl);
            foreach ([$databaseHost, $databasePort, $databaseName, $databaseUsername, $databasePassword] as $databaseValue) {
                Assert::assertIsString($databaseValue);
                Assert::assertNotSame('', $databaseValue);
            }

            $browser->assertSee('Installa AssestMe su questo dominio')
                ->assertSee('Basic Authentication')
                ->press('Avvia le verifiche')
                ->waitForText('Requisiti runtime e filesystem', 20)
                ->press('Continua')
                ->waitForText('Impostazioni di produzione', 20)
                ->assertMissing('input[name="application_name"]')
                ->assertMissing('input[name="weasyprint_binary"]')
                ->assertMissing('input[name="php_binary"]')
                ->type('application_url', $applicationUrl)
                ->type('timezone', 'Europe/Rome')
                ->type('backup_root', $releasePath.'/storage/backups')
                ->radio('database_driver', 'mysql')
                ->press('Continua al database')
                ->waitForText('Configura MySQL / MariaDB', 20)
                ->assertPresent('[data-dusk="database-step"]')
                ->assertMissing('input[name="dump_binary"]')
                ->assertMissing('input[name="restore_binary"]')
                ->assertInputValue('database_driver', 'mysql')
                ->type('database_host', $databaseHost)
                ->type('database_port', $databasePort)
                ->type('database_name', $databaseName)
                ->type('database_username', $databaseUsername)
                ->type('database_password', $databasePassword)
                ->type('database_socket', '')
                ->assertInputValue('database_charset', 'utf8mb4')
                ->assertInputValue('database_collation', 'utf8mb4_unicode_ci');

            $this->advancePastDatabaseStep($browser);

            $browser->assertPathIs('/install/administrator')
                ->assertPresent('[data-dusk="administrator-step"]')
                ->type('name', 'CI Administrator')
                ->type('email', 'admin@assestme.invalid')
                ->type('password', $password)
                ->type('password_confirmation', $password);

            $this->submitFinalization($browser);

            $browser->assertPresent('[data-dusk="installation-complete"]')
                ->assertSee('Scheduler')
                ->assertSee('CloudPanel')
                ->assertSee('cPanel')
                ->assertSee('Plesk')
                ->click('[data-dusk="scheduler-shell"]')
                ->assertSee('crontab -e')
                ->assertSee((string) realpath(PHP_BINARY))
                ->assertSee($releasePath.'/artisan schedule:run')
                ->visit('/install')
                ->assertSee('404')
                ->visit('/admin/login')
                ->waitFor('input[type="email"]', 20);

            $emailInput = $browser->element('input[type="email"]');
            $passwordInput = $browser->element('input[type="password"]');
            Assert::assertNotNull($emailInput);
            Assert::assertNotNull($passwordInput);
            $emailInput->clear();
            $passwordInput->clear();
            $emailInput->sendKeys('admin@assestme.invalid');
            $passwordInput->sendKeys($password);

            $browser->press('Accedi')
                ->waitForLocation('/admin', 30)
                ->assertPathIs('/admin')
                ->visit('/admin/settings/diagnostics')
                ->waitFor('[data-dusk="application-diagnostics"]', 30)
                ->assertSee('Diagnostica AssestMe')
                ->assertSee('MariaDB');
        });
    }

    private function submitFinalization(Browser $browser): void
    {
        $navigationToken = 'assestmeFinalize'.bin2hex(random_bytes(8));
        $browser->driver->executeScript("window['{$navigationToken}'] = true;");

        try {
            $browser->press('Installa e chiudi l’installer');
        } catch (Throwable $exception) {
            $this->failFinalization(
                $browser,
                'Installer finalization submit failed in the browser: '.$exception::class.'.',
            );
        }

        $deadline = microtime(true) + 30;

        do {
            if (! $this->releaseServerIsListening()) {
                $this->failFinalization(
                    $browser,
                    "Installer finalization lost the release HTTP server.\nRelease server is no longer reachable.",
                );
            }

            if ($this->browserHasNetworkError($browser)) {
                $this->failFinalization(
                    $browser,
                    "Installer finalization lost the release HTTP server.\nRelease server is no longer reachable.",
                );
            }

            $diagnosticFailures = [];
            $complete = $this->diagnosticText(
                $browser,
                '[data-dusk="installation-complete"]',
                $diagnosticFailures,
            );

            if ($complete !== null) {
                return;
            }

            $currentUrl = $this->currentUrl($browser, $diagnosticFailures);
            $path = parse_url($currentUrl, PHP_URL_PATH);
            $navigationCompleted = $this->navigationCompleted($browser, $navigationToken);

            if ($navigationCompleted && $path === '/install/administrator') {
                $installerError = $this->diagnosticText(
                    $browser,
                    '[data-dusk="installation-error"]',
                    $diagnosticFailures,
                );

                $this->failFinalization(
                    $browser,
                    'Installer finalization returned to [/install/administrator].'
                    ."\nInstaller error: ".($installerError ?? '[none displayed]'),
                );
            }

            if ($navigationCompleted) {
                $this->failFinalization(
                    $browser,
                    'Installer finalization navigated to an unexpected path ['
                    .(is_string($path) ? $path : 'unavailable').'].',
                );
            }

            usleep(250_000);
        } while (microtime(true) < $deadline);

        $this->failFinalization(
            $browser,
            'Installer finalization did not complete within 30 seconds; the request is blocked.',
        );
    }

    private function advancePastDatabaseStep(Browser $browser): void
    {
        try {
            $browser->waitForReload(
                static fn (Browser $browser): Browser => $browser->click('[data-dusk="database-submit"]'),
                20,
            );
        } catch (TimeoutException) {
            $this->failDatabaseStep(
                $browser,
                'Database installer navigation did not complete within 20 seconds; the request may be blocked.',
            );
        }

        $path = parse_url($browser->driver->getCurrentURL(), PHP_URL_PATH);

        if ($path === '/install/administrator') {
            return;
        }

        if ($path === '/install/database') {
            $this->failDatabaseStep($browser, 'Database installer remained on [/install/database].');
        }

        $this->failDatabaseStep(
            $browser,
            'Database installer navigated to an unexpected path ['.(is_string($path) ? $path : 'unavailable').'].',
        );
    }

    private function failDatabaseStep(Browser $browser, string $reason): never
    {
        $diagnosticFailures = [];
        $currentUrl = $this->currentUrl($browser, $diagnosticFailures);
        $installerError = $this->diagnosticText($browser, '[data-dusk="installation-error"]', $diagnosticFailures);
        $validationErrors = $this->diagnosticText($browser, '[data-dusk="validation-errors"]', $diagnosticFailures);

        foreach ([
            'screenshot' => static fn () => $browser->screenshot('release-installer-database-failure'),
            'DOM source' => static fn () => $browser->storeSource('release-installer-database-failure'),
            'console log' => static fn () => $browser->storeConsoleLog('release-installer-database-failure'),
        ] as $label => $capture) {
            try {
                $capture();
            } catch (Throwable $exception) {
                $diagnosticFailures[] = $label.': '.$exception::class;
            }
        }

        $message = implode("\n", [
            $reason,
            "Current URL: {$currentUrl}",
            'Installer error: '.($installerError ?? '[none displayed]'),
            'Validation errors: '.($validationErrors ?? '[none displayed]'),
        ]);

        if ($diagnosticFailures !== []) {
            $message .= "\nDiagnostic capture failures: ".implode(', ', $diagnosticFailures);
        }

        Assert::fail($message);
    }

    private function failFinalization(Browser $browser, string $reason): never
    {
        $diagnosticFailures = [];
        $currentUrl = $this->currentUrl($browser, $diagnosticFailures);
        $installerError = $this->diagnosticText($browser, '[data-dusk="installation-error"]', $diagnosticFailures);

        foreach ([
            'screenshot' => static fn () => $browser->screenshot('release-installer-finalization-failure'),
            'DOM source' => static fn () => $browser->storeSource('release-installer-finalization-failure'),
            'console log' => static fn () => $browser->storeConsoleLog('release-installer-finalization-failure'),
        ] as $label => $capture) {
            try {
                $capture();
            } catch (Throwable $exception) {
                $diagnosticFailures[] = $label.': '.$exception::class;
            }
        }

        $message = implode("\n", [
            $reason,
            "Current URL: {$currentUrl}",
            'Installer error: '.($installerError ?? '[none displayed]'),
        ]);

        if ($diagnosticFailures !== []) {
            $message .= "\nDiagnostic capture failures: ".implode(', ', $diagnosticFailures);
        }

        Assert::fail($message);
    }

    private function releaseServerIsListening(): bool
    {
        $url = (string) config('app.url');
        $host = parse_url($url, PHP_URL_HOST);
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $port = parse_url($url, PHP_URL_PORT);

        if (! is_string($host) || $host === '') {
            return false;
        }

        if (! is_int($port)) {
            $port = $scheme === 'https' ? 443 : 80;
        }

        $socket = @fsockopen($host, $port, $errorCode, $errorMessage, 0.2);

        if (! is_resource($socket)) {
            return false;
        }

        fclose($socket);

        return true;
    }

    private function browserHasNetworkError(Browser $browser): bool
    {
        try {
            $currentUrl = $browser->driver->getCurrentURL();
            $source = $browser->driver->getPageSource();

            return str_starts_with($currentUrl, 'chrome-error://')
                || str_contains($source, 'ERR_CONNECTION_REFUSED')
                || str_contains($source, 'chrome-error://chromewebdata/')
                || str_contains($source, 'main-frame-error');
        } catch (Throwable) {
            return ! $this->releaseServerIsListening();
        }
    }

    private function navigationCompleted(Browser $browser, string $navigationToken): bool
    {
        try {
            return $browser->driver->executeScript(
                "return typeof window['{$navigationToken}'] === 'undefined';",
            ) === true;
        } catch (Throwable) {
            return true;
        }
    }

    /** @param list<string> $diagnosticFailures */
    private function currentUrl(Browser $browser, array &$diagnosticFailures): string
    {
        try {
            return $browser->driver->getCurrentURL();
        } catch (Throwable $exception) {
            $diagnosticFailures[] = 'current URL: '.$exception::class;

            return '[unavailable]';
        }
    }

    /** @param list<string> $diagnosticFailures */
    private function diagnosticText(Browser $browser, string $selector, array &$diagnosticFailures): ?string
    {
        try {
            $element = $browser->element($selector);

            if ($element === null || ! $element->isDisplayed()) {
                return null;
            }

            $text = trim($element->getText());

            return $text !== '' ? $text : null;
        } catch (Throwable $exception) {
            $diagnosticFailures[] = "selector {$selector}: ".$exception::class;

            return null;
        }
    }
}
