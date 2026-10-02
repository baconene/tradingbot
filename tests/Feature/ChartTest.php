<?php
namespace Tests\Feature;

use App\MarketData\CandleImporter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ChartTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_chart_is_read_only_and_execution_disabled(): void
    {
        $this->get('/api/chart')->assertOk()->assertJsonPath('execution_enabled', false)
            ->assertJsonPath('points', [])->assertJsonPath('live', false);
    }

    public function test_chart_uses_completed_candles_and_computed_indicators(): void
    {
        $start = CarbonImmutable::parse('2025-01-01 00:00:00', 'UTC')->getTimestampMs();
        $rows = [];
        for ($i = 0; $i < 510; $i++) {
            $ms = $start + $i * 3600000;
            $close = 100000 + $i * 10;
            $rows[] = [$ms, (string) ($close - 5), (string) ($close + 20),
                (string) ($close - 20), (string) $close, '100',
                $ms + 3599999, '10000', 100, '0', '0', '0'];
        }
        app(CandleImporter::class)->import($rows, CarbonImmutable::createFromTimestampMs($start + 511 * 3600000, 'UTC'));
        $response = $this->get('/api/chart')->assertOk()->assertJsonPath('execution_enabled', false)
            ->assertJsonPath('has_more', true);
        $this->assertCount(240, $response->json('points'));
        $this->assertNotNull($response->json('points.239.ema20'));
        $this->assertNotNull($response->json('points.239.rsi14'));
        $older = $this->get('/api/chart?before='.intdiv($start + 270 * 3600000, 1000))
            ->assertOk()->json('points');
        $this->assertNotEmpty($older);
        $this->assertLessThan($response->json('points.0.time'), end($older)['time']);
        $this->get('/api/chart?before=not-a-date')->assertStatus(422);
    }
    public function test_research_reports_do_not_replace_standard_trade_ledger_or_chart_markers(): void
    {
        $now = now();
        $base = ['data_start'=>'2025-01-01 00:00:00','data_end'=>'2025-01-15 00:00:00',
            'data_hash'=>str_repeat('a',64),'created_at'=>$now,'updated_at'=>$now];
        $standardId=DB::table('backtest_runs')->insertGetId($base+[
            'strategy_version'=>\App\Strategies\MomentumBreakout::VERSION,
            'results'=>json_encode(['trade_count'=>1,'trades'=>[[
                'entry_index'=>201,'exit_index'=>203,'entry'=>100.0,'exit'=>104.0,
                'pnl'=>3.0,'quantity'=>1.0,'exit_reason'=>'target'
            ]]])
        ]);
        foreach(['MBR-001-optimization-research','MBR-001-walk-forward-research'] as $version){
            DB::table('backtest_runs')->insert($base+[
                'strategy_version'=>$version,'results'=>json_encode(['mode'=>'research_only','folds'=>[]])
            ]);
        }
        $this->get('/api/research/backtests')->assertOk()
            ->assertJsonPath('latest_backtest.id',$standardId)
            ->assertJsonPath('latest_backtest.results.trade_count',1)
            ->assertJsonCount(1,'latest_backtest.results.trades');
        $this->get('/api/chart')->assertOk()->assertJsonPath('backtest_id',$standardId)
            ->assertJsonCount(2,'markers');
    }

}
