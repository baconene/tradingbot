<?php
namespace Tests\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
final class FuturesResearchTest extends TestCase {
 use RefreshDatabase;
 public function test_empty_market_and_ledger_are_explicitly_unavailable(): void {
  $this->getJson('/api/futures/market')->assertOk()->assertJsonPath('stale',true)
   ->assertJsonPath('execution_enabled',false)->assertJsonCount(0,'timeframes.1m');
  $this->getJson('/api/research/backtest')->assertOk()->assertJsonPath('run',null);
 }
 public function test_no_order_endpoints_are_exposed(): void {
  $this->postJson('/api/futures/orders',[])->assertNotFound();
 }
}