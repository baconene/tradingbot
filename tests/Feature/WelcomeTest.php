<?php
namespace Tests\Feature;
use Tests\TestCase;
final class WelcomeTest extends TestCase {
 public function test_dashboard_loads(): void {$this->withoutVite();$this->get('/')->assertOk();}
 public function test_old_spot_routes_remain_removed(): void {$this->get('/api/chart')->assertNotFound();}
}