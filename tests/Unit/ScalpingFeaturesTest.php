<?php
namespace Tests\Unit;
use App\MarketData\ScalpingFeatures;
use PHPUnit\Framework\TestCase;
final class ScalpingFeaturesTest extends TestCase {
 public function test_only_complete_contiguous_five_minute_candles_are_aggregated(): void {
  $m=[];for($i=0;$i<10;$i++){if($i===3)continue;$m[]=['open_ms'=>$i*60000,'close_ms'=>$i*60000+59999,
   'open'=>100,'high'=>102,'low'=>99,'close'=>101,'volume'=>2];}
  $f=new ScalpingFeatures;$out=$f->aggregate($m,5);
  $this->assertCount(1,$out);$this->assertSame(300000,$out[0]['open_ms']);$this->assertSame(10.,$out[0]['volume']);
 }
 public function test_missing_minute_breaks_contiguity(): void {
  $this->assertFalse((new ScalpingFeatures)->contiguous([['open_ms'=>0],['open_ms'=>120000]]));
 }
}