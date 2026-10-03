<?php
namespace App\Backtesting;
use App\MarketData\ScalpingFeatures;
use InvalidArgumentException;
final class ScalpingBacktester {
 public const VERSION='hhll-2r-v1';
 public function __construct(private ScalpingFeatures $features){}
 public function run(array $minutes,array $options=[]): array {
  $p=array_replace(['initial_equity'=>1000.,'risk_pct'=>0.005,'rr'=>2.,'fee_rate'=>0.0005,
   'slippage_bps'=>5.,'max_hold_minutes'=>60,'max_notional_pct'=>0.5],$options);
  if(count($minutes)<900||!$this->features->contiguous($minutes))throw new InvalidArgumentException('At least 900 contiguous closed 1m bars required');
  if($p['initial_equity']<=0||$p['risk_pct']<=0||$p['risk_pct']>0.01||$p['rr']<1||$p['rr']>5||
   $p['fee_rate']<0||$p['slippage_bps']<0||$p['max_hold_minutes']<1||$p['max_hold_minutes']>240||
   $p['max_notional_pct']<=0||$p['max_notional_pct']>1)throw new InvalidArgumentException('Invalid backtest parameters');
  $five=$this->features->indicators($this->features->aggregate($minutes,5));
  $fifteen=$this->features->indicators($this->features->aggregate($minutes,15));
  $equity=(float)$p['initial_equity'];$peak=$equity;$maxDd=0.;$trades=[];$curve=[['ms'=>$minutes[0]['open_ms'],'equity'=>$equity]];
  $nextMs=0;$trendIndex=0;$trend=null;$wins=0;$firstMs=(int)$minutes[0]['open_ms'];
  foreach($five as $i=>$signal){
   $signalClose=(int)$signal['close_ms'];
   while($trendIndex<count($fifteen)&&$fifteen[$trendIndex]['close_ms']<=$signalClose)$trend=$fifteen[$trendIndex++];
   if($i<50||$signal['previous_high']===null||$signal['ema50']===null||$trend===null||$trend['ema50']===null||$signalClose<$nextMs)continue;
   $long=$signal['close']>$signal['previous_high']&&$signal['ema20']>$signal['ema50']&&$trend['close']>$trend['ema50'];
   $short=$signal['close']<$signal['previous_low']&&$signal['ema20']<$signal['ema50']&&$trend['close']<$trend['ema50'];
   if(!$long&&!$short)continue;$side=$long?'long':'short';
   $stop=$long?(float)$signal['previous_low']:(float)$signal['previous_high'];
   $entryIndex=intdiv($signalClose+1-$firstMs,60000);
   if($entryIndex<0||$entryIndex>=count($minutes))break;
   $entryBar=$minutes[$entryIndex];if($entryBar['open_ms']!==$signalClose+1)continue;
   $slip=$p['slippage_bps']/10000;$entry=$entryBar['open']*($long?1+$slip:1-$slip);
   $distance=abs($entry-$stop);
   if(($long&&$stop>=$entry)||($short&&$stop<=$entry)||$distance<$entry*.0001)continue;
   $qty=min($equity*$p['risk_pct']/$distance,$equity*$p['max_notional_pct']/$entry);
   if($qty<=0)continue;$target=$entry+($long?1:-1)*$distance*$p['rr'];
   $last=min(count($minutes)-1,$entryIndex+(int)$p['max_hold_minutes']-1);
   $exitIndex=$last;$reason='time';$raw=$minutes[$last]['close'];
   for($j=$entryIndex;$j<=$last;$j++){
    $bar=$minutes[$j];$stopHit=$long?$bar['low']<=$stop:$bar['high']>=$stop;
    $targetHit=$long?$bar['high']>=$target:$bar['low']<$target;
    if($stopHit){$exitIndex=$j;$reason='stop';$raw=$stop;break;}
    if($targetHit){$exitIndex=$j;$reason='target';$raw=$target;break;}
   }
   $exit=$raw*($long?1-$slip:1+$slip);$gross=($exit-$entry)*$qty*($long?1:-1);
   $fees=($entry+$exit)*$qty*$p['fee_rate'];$net=$gross-$fees;$equity+=$net;
   $peak=max($peak,$equity);$maxDd=max($maxDd,($peak-$equity)/$peak);if($net>0)$wins++;
   $trades[]=['side'=>$side,'signal_ms'=>$signal['open_ms'],'entry_ms'=>$entryBar['open_ms'],
    'exit_ms'=>$minutes[$exitIndex]['open_ms'],'entry'=>$entry,'stop'=>$stop,'target'=>$target,'exit'=>$exit,
    'quantity'=>$qty,'gross_pnl'=>$gross,'fees'=>$fees,'net_pnl'=>$net,'r_multiple'=>$net/($distance*$qty),
    'exit_reason'=>$reason,'context'=>['five_rsi14'=>$signal['rsi14'],'five_atr14'=>$signal['atr14'],
     'fifteen_ema50'=>$trend['ema50']]];
   $curve[]=['ms'=>$minutes[$exitIndex]['open_ms'],'equity'=>$equity];
   $nextMs=$minutes[$exitIndex]['close_ms']+1;
  }
  $n=count($trades);
  return ['strategy'=>self::VERSION,'parameters'=>$p,'trades'=>$trades,'equity_curve'=>$curve,
   'metrics'=>['initial_equity'=>$p['initial_equity'],'final_equity'=>$equity,
    'net_return_pct'=>($equity/$p['initial_equity']-1)*100,'max_realized_drawdown_pct'=>$maxDd*100,
    'trades'=>$n,'wins'=>$wins,'losses'=>$n-$wins,'win_rate_pct'=>$n?$wins/$n*100:null,
    'rr_target'=>$p['rr'],'average_net_r'=>$n?array_sum(array_column($trades,'r_multiple'))/$n:null,
    'total_fees'=>array_sum(array_column($trades,'fees')),'funding_included'=>false,
    'liquidation_modeled'=>false,'execution_enabled'=>false]];
 }
}