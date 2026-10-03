<?php
namespace Tests\Unit;
use App\Backtesting\ScalpingBacktester;
use App\MarketData\ScalpingFeatures;
use PHPUnit\Framework\TestCase;
final class ScalpingBacktesterTest extends TestCase {
 public function test_flat_market_has_no_fabricated_trades_or_win_rate(): void {
  $minutes=[];for($i=0;$i<900;$i++)$minutes[]=['open_ms'=>$i*60000,'close_ms'=>$i*60000+59999,
   'open'=>100.,'high'=>100.1,'low'=>99.9,'close'=>100.,'volume'=>1.];
  $r=(new ScalpingBacktester(new ScalpingFeatures))->run($minutes);
  $this->assertSame(0,$r['metrics']['trades']);$this->assertNull($r['metrics']['win_rate_pct']);
  $this->assertSame(1000.,$r['metrics']['final_equity']);$this->assertFalse($r['metrics']['execution_enabled']);
 }
 public function test_missing_minute_fails_closed(): void {
  $minutes=[];for($i=0;$i<900;$i++)$minutes[]=['open_ms'=>$i*60000+($i>=400?60000:0),
   'close_ms'=>$i*60000+59999+($i>=400?60000:0),'open'=>100.,'high'=>101.,'low'=>99.,'close'=>100.,'volume'=>1.];
  $this->expectException(\InvalidArgumentException::class);
  (new ScalpingBacktester(new ScalpingFeatures))->run($minutes);
 }
}