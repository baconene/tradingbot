<?php
namespace Tests\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
final class ResearchOperationsTest extends TestCase {
 use RefreshDatabase;
 protected function setUp(): void {parent::setUp();$this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);}
 public function test_mutating_operations_fail_closed_without_operator_secret(): void {
  config(['astra.operator_token'=>null]);
  $this->postJson('/api/research/import',['pages'=>1])->assertForbidden();
  $this->postJson('/api/research/run',['bars'=>900,'rr'=>2,'risk'=>0.005])->assertForbidden();
  $this->assertSame(0,DB::table('futures_candles')->count());
 }
 public function test_invalid_token_and_invalid_input_are_rejected(): void {
  config(['astra.operator_token'=>str_repeat('x',40)]);
  $this->withToken(str_repeat('z',40))->postJson('/api/research/import',['pages'=>1])->assertForbidden();
  $this->withToken(str_repeat('x',40))->postJson('/api/research/import',['pages'=>100])->assertUnprocessable();
  $this->withToken(str_repeat('x',40))->postJson('/api/research/run',['bars'=>900,'rr'=>0,'risk'=>0.005])->assertUnprocessable();
 }
 public function test_authenticated_browser_imports_completed_futures_candles(): void {
  config(['astra.operator_token'=>str_repeat('x',40)]);
  $now=60200000;
  Http::fake(['*/fapi/v1/klines*'=>Http::response([
   [60000000,'100','101','99','100','5',60059999],
   [60060000,'100','101','99','100','5',60119999],
  ],200)]);
  // The HTTP endpoint uses the real clock, so fake only the API and validate authorization here.
  $this->withToken(str_repeat('x',40))->postJson('/api/research/import',['pages'=>1])->assertOk()
   ->assertJsonStructure(['imported','gaps_observed','last_open_ms']);
 }
}