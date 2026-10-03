<?php
namespace Tests\Unit;
use App\Futures\FuturesRiskPreflight;
use App\Futures\StrategyConfiguration;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
final class FuturesFoundationTest extends TestCase
{
 public function test_100x_is_research_configuration_only(): void
 {
  $config=new StrategyConfiguration;
  $p=$config->normalize(['leverage'=>100,'reward_risk'=>3.0]);
  $this->assertSame(100,$p['leverage']);
  $this->assertSame(3.0,$p['reward_risk']);
  $this->assertSame($config->hash($p),$config->hash(array_reverse($p,true)));
 }
 public function test_invalid_leverage_is_rejected(): void
 {
  $this->expectException(InvalidArgumentException::class);
  (new StrategyConfiguration)->normalize(['leverage'=>101]);
 }
 public function test_unreconciled_or_latched_risk_rejects_even_at_100x(): void
 {
  $state=['equity'=>1000,'available_margin'=>1000,'maintenance_margin'=>0,
   'gross_notional'=>0,'daily_pnl'=>0,'daily_reference'=>1000,'high_water'=>1000,
   'reconciled'=>false,'kill_latched'=>true];
  $order=['side'=>'long','entry'=>100,'stop'=>99,'quantity'=>1,'leverage'=>100,
   'min_notional'=>5,'step_size'=>.01,'min_qty'=>.01,'estimated_round_trip_fees'=>.2,
   'estimated_funding'=>.1,'manual_approved'=>true];
  $risk=new FuturesRiskPreflight;
  $this->assertSame('locked_or_unreconciled',$risk->evaluate($state,$order)['reason']);
  $state['reconciled']=true;$state['kill_latched']=false;
  $this->assertTrue($risk->evaluate($state,$order)['allowed']);
  $order['quantity']=3;
  $this->assertSame('stop_risk_with_costs',$risk->evaluate($state,$order)['reason']);
 }
}
