<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders(): void
    {
        $this->withoutVite();
        $this->get('/')->assertOk();
    }

    public function test_public_status_never_exposes_secrets_or_enables_execution(): void
    {
        $this->get('/api/status')->assertOk()
            ->assertJsonPath('execution_enabled', false)
            ->assertJsonPath('kill_switch', 'locked')
            ->assertDontSee('api_secret');
    }
}
