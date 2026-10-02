<?php
namespace Tests\Unit;

use App\Strategies\MomentumBreakout;
use PHPUnit\Framework\TestCase;

final class BreakoutFilterTest extends TestCase
{
    public function test_research_filters_reject_weak_breakout_and_wide_signal_bar(): void
    {
        $f=['close'=>110,'ema20'=>105,'ema50'=>100,'rsi14'=>65,'atr14'=>10,
            'previous_20_high'=>109,'relative_volume'=>2,'signal_range_atr'=>2.5];
        $strategy=new MomentumBreakout();
        $this->assertTrue($strategy->signal($f)['entry']);
        $this->assertSame('breakout_atr_filter',$strategy->signal($f,['min_breakout_atr'=>.2])['reason']);
        $this->assertSame('range_atr_filter',$strategy->signal($f,['max_signal_range_atr'=>2])['reason']);
    }
}
