<?php
namespace Tests\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
final class ResearchExportTest extends TestCase {
 use RefreshDatabase;
 public function test_export_is_disabled_without_configured_token(): void {
  $this->get('/api/research/backtests/export')->assertUnauthorized();
 }
 public function test_export_requires_token_and_is_read_only(): void {
  config()->set('astra.research_export_token','test-secret');
  DB::table('backtest_runs')->insert(['strategy_version'=>'MBR-001-v1',
   'data_start'=>'2025-01-01 00:00:00','data_end'=>'2025-01-15 00:00:00',
   'data_hash'=>str_repeat('a',64),'results'=>json_encode(['trade_count'=>0,'trades'=>[]]),
   'created_at'=>now(),'updated_at'=>now()]);
  $this->withToken('wrong')->get('/api/research/backtests/export')->assertUnauthorized();
  $this->withToken('test-secret')->get('/api/research/backtests/export')
   ->assertOk()->assertJsonPath('execution_enabled',false)
   ->assertJsonPath('runs.0.results.trade_count',0)
   ->assertHeader('Cache-Control','no-store');
 }
}
