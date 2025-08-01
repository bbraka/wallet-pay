<?php

namespace Tests\Browser\Merchant;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class DuskFixTest extends DuskTestCase
{
    /** @test */  
    public function can_access_homepage()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                    ->pause(1000) // Give page time to load
                    ->screenshot('homepage-test');
            
            // Just check that we got a response
            $source = $browser->driver->getPageSource();
            $this->assertNotEmpty($source);
        });
    }

    /** @test */
    public function can_access_merchant_login()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/merchant/login')
                    ->assertPresent('#merchant-app')
                    ->screenshot('merchant-login-test');
        });
    }
}