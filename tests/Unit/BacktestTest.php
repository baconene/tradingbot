<?php
namespace Tests\Unit;
use App\Backtesting\HourlyBacktester;
use App\MarketData\HourlyFeatures;
use App\Strategies\MomentumBreakout;
use PHPUnit\Framework\TestCase;
final class BacktestTest extends TestCase {
    public function test_backtest_has_no_fabricated_trades_on_flat_series(): void {
        $candles=[];for($i=0;$i<280;$i++) $candles[]=['open_ms'=>$i*3600000,'open'=>100,'high'=>101,'low'=>99,'close'=>100,'volume'=>100];
        $result=(new HourlyBacktester(new HourlyFeatures(),new MomentumBreakout()))->run($candles);
        $this->assertSame(0,$result['trade_count']);$this->assertSame(1000.0,$result['final_realized_equity']);
        $this->assertNull($result['win_rate_pct']);
    }
    public function test_discontinuous_history_is_rejected(): void {
        $candles=[];for($i=0;$i<280;$i++) $candles[]=['open_ms'=>($i+($i>=120?1:0))*3600000,'open'=>100,'high'=>101,'low'=>99,'close'=>100,'volume'=>100];
        $this->expectException(\InvalidArgumentException::class);
        (new HourlyBacktester(new HourlyFeatures(),new MomentumBreakout()))->run($candles);
    }
}
