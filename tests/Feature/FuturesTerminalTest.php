<?php
namespace Tests\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
final class FuturesTerminalTest extends TestCase
{
 use RefreshDatabase;
 public function test_terminal_is_read_only_and_fail_closed(): void
 {
  $this->get('/api/futures/terminal')->assertOk()
   ->assertJsonPath('execution_enabled',false)
   ->assertJsonPath('risk.kill_latched',true)
   ->assertJsonPath('readiness.exchange_execution',false);
 }
 public function test_unauthorized_config_creation_is_denied(): void
 {
  $this->postJson('/api/futures/configs',['name'=>'untrusted','parameters'=>[]])->assertForbidden();
 }
}
