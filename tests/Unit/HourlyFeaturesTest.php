<?php
namespace Tests\Unit;
use App\MarketData\HourlyFeatures;
use PHPUnit\Framework\TestCase;
final class HourlyFeaturesTest extends TestCase {
    private function candles(int $n=220): array {
        $rows=[];for($i=0;$i<$n;$i++) {
            $close=100+$i*.1;
            $rows[]=['open_ms'=>$i*3600000,'open'=>$close,'high'=>$close+2,'low'=>$close-2,
                'close'=>$close,'volume'=>$i===$n-1?200:100];
        }return $rows;
    }
    public function test_indicators_are_finite_and_breakout_excludes_current_candle(): void {
        $features=(new HourlyFeatures())->calculate($this->candles());
        $this->assertEqualsWithDelta(121.9,$features['close'],.001);
        $this->assertEqualsWithDelta(2.0,$features['relative_volume'],.001);
        $this->assertEqualsWithDelta(123.8,$features['previous_20_high'],.001);
        $this->assertEqualsWithDelta(4.0,$features['atr14'],.001);
        $this->assertGreaterThan(0,$features['ema20']);
        $this->assertSame(100.0,$features['rsi14']);
        $this->assertNull($features['spread_bps']);
    }
    public function test_rejects_insufficient_history(): void {
        $this->expectException(\InvalidArgumentException::class);
        (new HourlyFeatures())->calculate($this->candles(199));
    }
    public function test_rejects_historical_gap(): void {
        $rows=$this->candles();$rows[100]['open_ms']+=1000;
        $this->expectException(\InvalidArgumentException::class);
        (new HourlyFeatures())->calculate($rows);
    }
}
