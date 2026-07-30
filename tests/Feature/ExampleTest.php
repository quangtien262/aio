<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_redirects_to_the_default_localized_home(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('site.home', ['locale' => 'vi']));
        $this->get('/vi')->assertSuccessful();
    }
}
