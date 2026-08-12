<?php

declare(strict_types=1);

namespace Tests\Browser;

use Facebook\WebDriver\Exception\TimeoutException;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Assert;
use Tests\DuskTestCase;
use Throwable;

final class CloudPanelInstallationTest extends DuskTestCase
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
            Assert::assertIsString($releasePath);
            Assert::assertNotSame('', $releasePath);
            Assert::assertIsString($password);
            Assert::assertNotSame('', $password);

            $browser->assertSee('Installa AssestMe su questo dominio')
                ->assertSee('Basic Authentication')
                ->press('Avvia le verifiche')
                ->waitForText('Requisiti runtime e filesystem', 20)
                ->press('Continua')
                ->waitForText('Impostazioni di produzione', 20)
                ->assertMissing('input[name="application_name"]')
                ->assertMissing('input[name="weasyprint_binary"]')
                ->assertMissing('input[name="php_binary"]')
                ->type('application_url', rtrim((string) config('app.url'), '/'))
                ->type('timezone', 'Europe/Rome')
                ->type('backup_root', $releasePath.'/storage/backups')
                ->radio('database_driver', 'sqlite')
                ->press('Continua al database')
                ->waitForText('Configura SQLite', 20)
                ->assertPresent('[data-dusk="database-step"]')
                ->assertMissing('input[name="dump_binary"]')
                ->assertMissing('input[name="restore_binary"]')
                ->type('sqlite_path', $releasePath.'/storage/app/database/database.sqlite');

            $this->advancePastDatabaseStep($browser);

            $browser->assertPathIs('/install/administrator')
                ->assertPresent('[data-dusk="administrator-step"]')
                ->type('name', 'CI Administrator')
                ->type('email', 'admin@assestme.invalid')
                ->type('password', $password)
                ->type('password_confirmation', $password)
                ->press('Installa e chiudi l’installer')
                ->waitForText('AssestMe è pronto', 120)
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
                ->assertSee('SQLite');
        });
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
            'screenshot' => static fn () => $browser->screenshot('cloudpanel-installer-database-failure'),
            'DOM source' => static fn () => $browser->storeSource('cloudpanel-installer-database-failure'),
            'console log' => static fn () => $browser->storeConsoleLog('cloudpanel-installer-database-failure'),
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
