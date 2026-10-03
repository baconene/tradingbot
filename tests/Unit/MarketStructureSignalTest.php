<?php
namespace Tests\Unit;
use App\Research\MarketStructureSignal;
use PHPUnit\Framework\TestCase;
final class MarketStructureSignalTest extends TestCase
{
    public function test_confirmed_higher_high_higher_low_produces_long_levels(): void
    {
        $candles=[];
        for($i=0;$i<22;$i++)$candles[]=['open_ms'=>$i*3600000,'high'=>100+$i,'low'=>80+$i,'close'=>90+$i];
        $candles[21]['close']=122;
        $signal=(new MarketStructureSignal())->analyze($candles,20,2);
        $this->assertSame('confirmed',$signal['state']);
        $this->assertSame('long',$signal['direction']);
        $this->assertSame(122.0,$signal['entry']);
        $this->assertSame(80.0,$signal['stop']);
        $this->assertSame(206.0,$signal['target']);
        $this->assertFalse($signal['execution_enabled']);
    }
    public function test_insufficient_data_does_not_create_entry(): void
    {
        $signal=(new MarketStructureSignal())->analyze([],20,2);
        $this->assertNull($signal['entry']);
    }
}
