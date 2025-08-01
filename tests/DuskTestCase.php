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
        sleep(1);
        
        // Start new server with test environment - use nohup for better process management
        $command = sprintf(
            'nohup php artisan serve --host=0.0.0.0 --port=8089 --env=dusk.local > /tmp/dusk_server.log 2>&1 & echo $!'
        );
        
        $pid = exec($command);
        static::$serverProcess = $pid;
        echo "Started test server with PID: $pid\n";
        
        // Wait for server to be ready with timeout
        $maxAttempts = 10;
        $attempt = 0;
        
        while ($attempt < $maxAttempts) {
            $attempt++;
            sleep(1);
            
            // Check if server is responding
            $checkCommand = 'curl -s -o /dev/null -w "%{http_code}" --connect-timeout 2 --max-time 5 http://localhost:8089 2>/dev/null || echo "timeout"';
            $httpCode = exec($checkCommand);
            
            if ($httpCode !== 'timeout' && $httpCode !== '000') {
                echo "Test server ready after {$attempt} attempts. HTTP code: $httpCode\n";
                break;
            }
            
            if ($attempt === $maxAttempts) {
                echo "Test server failed to start after $maxAttempts attempts\n";
                exec("cat /tmp/dusk_server.log");
            }
        }
        
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
