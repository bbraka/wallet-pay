<?php

namespace Tests;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Support\Collection;
use Laravel\Dusk\TestCase as BaseTestCase;
use PHPUnit\Framework\Attributes\BeforeClass;

abstract class DuskTestCase extends BaseTestCase
{
    /**
     * The process running the test server.
     */
    protected static $serverProcess;

    /**
     * Prepare for Dusk test execution.
     */
    #[BeforeClass]
    public static function prepare(): void
    {
        if (! static::runningInSail()) {
            static::startChromeDriver(['--port=9515']);
        }
        
        // Start test server on port 8089
        static::startTestServer();
    }
    
    /**
     * Start the test server.
     */
    protected static function startTestServer(): void
    {
        if (static::$serverProcess) {
            return;
        }
        
        // Kill any existing server on port 8089
        exec('fuser -k 8089/tcp 2>/dev/null || true');
        
        // Start new server with test environment
        $command = sprintf(
            'APP_ENV=dusk.local php artisan serve --host=0.0.0.0 --port=8089 > /dev/null 2>&1 & echo $!'
        );
        
        $pid = exec($command);
        echo "Started test server with PID: $pid\n";
        
        // Give server time to start
        sleep(3);
        
        // Check if server is running
        $checkCommand = 'curl -s -o /dev/null -w "%{http_code}" http://localhost:8089';
        $httpCode = exec($checkCommand);
        echo "Test server HTTP response code: $httpCode\n";
        
        register_shutdown_function(function () use ($pid) {
            exec("kill -9 $pid 2>/dev/null");
        });
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
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--disable-extensions',
            '--disable-background-timer-throttling',
            '--disable-backgrounding-occluded-windows',
            '--disable-renderer-backgrounding',
        ])->unless($this->hasHeadlessDisabled(), function (Collection $items) {
            return $items->merge([
                '--disable-gpu',
                '--headless=new',
            ]);
        })->all());

        return RemoteWebDriver::create(
            $_ENV['DUSK_DRIVER_URL'] ?? env('DUSK_DRIVER_URL') ?? 'http://localhost:9515',
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY, $options
            )
        );
    }
}
