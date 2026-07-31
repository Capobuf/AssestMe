<?php

declare(strict_types=1);

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use PHPUnit\Framework\Assert;
use Tests\DuskTestCase;

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
                ->type('application_name', 'AssestMe')
                ->type('application_url', rtrim((string) config('app.url'), '/'))
                ->type('timezone', 'Europe/Rome')
                ->type('backup_root', $releasePath.'/storage/backups')
                ->type('weasyprint_binary', (string) getenv('LARAVEL_PDF_WEASYPRINT_BINARY'))
                ->type('php_binary', (string) realpath(PHP_BINARY))
                ->radio('database_driver', 'sqlite')
                ->press('Continua al database')
                ->waitForText('Configura SQLite', 20)
                ->type('sqlite_path', $releasePath.'/storage/app/database/database.sqlite')
                ->press('Testa realmente il database')
                ->waitForText('Crea l’unico amministratore', 20)
                ->type('name', 'CI Administrator')
                ->type('email', 'admin@assestme.invalid')
                ->type('password', $password)
                ->type('password_confirmation', $password)
                ->press('Installa e chiudi l’installer')
                ->waitForText('AssestMe è pronto', 120)
                ->assertSee('Scheduler CloudPanel')
                ->visit('/install')
                ->assertSee('404')
                ->visit('/admin/login')
                ->waitFor('input[type="email"]', 20);

            $emailInput = $browser->element('input[type="email"]');
            $passwordInput = $browser->element('input[type="password"]');
            Assert::assertNotNull($emailInput);
            Assert::assertNotNull($passwordInput);
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
}
