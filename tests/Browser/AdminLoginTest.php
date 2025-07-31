<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class AdminLoginTest extends DuskTestCase
{
    /**
     * Test admin login and permission page functionality
     */
    public function testAdminLoginAndPermissionPage()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/admin/login')
                    ->waitFor('input[name="email"]')
                    ->type('email', 'admin@example.com')  
                    ->type('password', 'password')
                    ->press('Login')
                    ->waitForLocation('/admin/dashboard')
                    ->assertSee('Dashboard');

            // Navigate to permissions page
            $browser->visit('/admin/permission')
                    ->waitFor('#crudTable', 10);

            // Check for JavaScript errors in console
            $logs = $browser->driver->manage()->getLog('browser');
            
            // Output console logs for debugging
            foreach ($logs as $log) {
                if ($log['level'] === 'SEVERE') {
                    echo "SEVERE ERROR: " . $log['message'] . "\n";
                }
            }

            // Check if DataTables initialization worked
            $browser->waitUntilMissing('.dataTables_processing', 15);
            
            // Try to get the actual content being served for DataTables files
            $jsFiles = [
                'https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js',
                'https://cdn.datatables.net/1.13.1/js/dataTables.bootstrap5.min.js'
            ];

            foreach ($jsFiles as $jsFile) {
                $browser->script("
                    fetch('$jsFile')
                        .then(response => response.text())
                        .then(data => {
                            console.log('JS File $jsFile content preview:', data.substring(0, 100));
                            if (data.includes('<html>') || data.includes('<!DOCTYPE')) {
                                console.error('ERROR: $jsFile is returning HTML instead of JavaScript');
                            }
                        })
                        .catch(error => console.error('Error fetching $jsFile:', error));
                ");
            }

            // Wait a moment for the fetch requests to complete
            $browser->pause(2000);

            // Get updated console logs
            $logs = $browser->driver->manage()->getLog('browser');
            foreach ($logs as $log) {
                echo "CONSOLE: " . $log['message'] . "\n";
            }

            // Check if the table shows data or empty state
            if ($browser->element('#crudTable tbody tr')) {
                $rowCount = count($browser->elements('#crudTable tbody tr'));
                echo "Table has $rowCount rows\n";
            } else {
                echo "No table rows found\n";
            }
        });
    }
}