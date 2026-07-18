<?php

declare(strict_types=1);

namespace Tests;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Support\Collection;
use Laravel\Dusk\TestCase as BaseTestCase;
use PHPUnit\Framework\Attributes\BeforeClass;
use RuntimeException;

abstract class DuskTestCase extends BaseTestCase
{
    /**
     * Settings migrations are tracked in Laravel's migrations table, so truncating
     * their values would leave later browser tests unable to rebuild typed settings.
     *
     * @var list<string>
     */
    protected array $exceptTables = ['settings'];

    /**
     * Prepare for Dusk test execution.
     */
    #[BeforeClass]
    public static function prepare(): void
    {
        $isolatedRoot = getenv('ASSESTME_TEST_ROOT');

        if (getenv('ASSESTME_TEST_ISOLATED') !== '1'
            || ! is_string($isolatedRoot)
            || $isolatedRoot === ''
            || ! is_file($isolatedRoot.'/.assestme-test-root')) {
            throw new RuntimeException(
                'Dusk requires the marked disposable environment created by scripts/dusk-isolated.sh.',
            );
        }

        if (static::configuredDriverUrl() === null) {
            $driverPath = $_ENV['DUSK_CHROMEDRIVER_PATH'] ?? getenv('DUSK_CHROMEDRIVER_PATH');

            if (is_string($driverPath) && $driverPath !== '') {
                static::useChromedriver($driverPath);
            }

            static::startChromeDriver(['--port=9515']);
        }
    }

    /**
     * Create the RemoteWebDriver instance.
     */
    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments(collect([
            $this->shouldStartMaximized() ? '--start-maximized' : '--window-size=1920,1080',
            '--disable-search-engine-choice-screen',
            '--disable-smooth-scrolling',
            // Remote Compose HTTP must remain a secure test origin for download diagnostics.
            '--unsafely-treat-insecure-origin-as-secure='.rtrim((string) config('app.url'), '/'),
            // Pipe transport avoids the Chromium snap stalling while ChromeDriver discovers its ephemeral debug port.
            '--remote-debugging-pipe',
        ])->when(function_exists('posix_geteuid') && posix_geteuid() === 0, function (Collection $items) {
            // Chrome refuses to create a Linux session as root unless its sandbox is explicitly disabled.
            return $items->push('--no-sandbox');
        })->unless($this->hasHeadlessDisabled(), function (Collection $items) {
            return $items->merge([
                '--disable-gpu',
                '--headless=new',
            ]);
        })->all());

        return RemoteWebDriver::create(
            static::configuredDriverUrl() ?? 'http://localhost:9515',
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY, $options
            )
        );
    }

    private static function configuredDriverUrl(): ?string
    {
        $driverUrl = $_ENV['DUSK_DRIVER_URL'] ?? getenv('DUSK_DRIVER_URL');

        return is_string($driverUrl) && trim($driverUrl) !== '' ? $driverUrl : null;
    }
}
