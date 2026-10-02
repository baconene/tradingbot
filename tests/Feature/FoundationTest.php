<?php
namespace Tests\Feature;
use Tests\TestCase;
final class FoundationTest extends TestCase {
    public function test_dashboard_renders(): void {
        $response = $this->get('/'); $response->assertOk();
    }
    public function test_public_status_never_exposes_secrets_or_enables_execution(): void {
        $response = $this->get('/api/status'); $response->assertOk()
            ->assertJsonPath('execution_enabled', false)
            ->assertJsonPath('kill_switch', 'locked')
            ->assertDontSee('api_secret');
    }
}
