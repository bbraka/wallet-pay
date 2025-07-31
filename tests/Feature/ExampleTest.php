<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        // Expect redirect to login or dashboard - both are valid
        $response->assertStatus(302);
    }
}
