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
        $response = $this->get('/');

        $response->assertRedirect(route('admin.login'));
    }

    public function test_ping_endpoint_returns_success_payload(): void
    {
        $response = $this->getJson('/api/ping');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'app',
                'timestamp',
            ]);
    }
}
