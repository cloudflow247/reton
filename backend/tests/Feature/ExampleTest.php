<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        // "/" is now an Inertia page whose root Blade calls @vite; stub Vite so
        // the smoke test doesn't depend on a built manifest.
        $this->withoutVite();

        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
