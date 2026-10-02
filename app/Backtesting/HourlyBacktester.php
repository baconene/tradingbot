<?php
namespace App\Backtesting;
use App\MarketData\HourlyFeatures;
use App\Strategies\MomentumBreakout;
use InvalidArgumentException;
final class HourlyBacktester {
    public function __construct(private HourlyFeatures $features, private MomentumBreakout $strategy) {}
    /** Closed-candle signal, earliest next-bar open fill. Ambiguous stop/target bars resolve against trader. */
    public function run(array $candles, float $initial=1000.0, float $feeRate=.001, float $slippageBps=5.0,array $parameters=[]): array {
        if ($initial<=0 || $feeRate<0 || $slippageBps<0) throw new InvalidArgumentException('Invalid assumptions');
        $equity=$initial;$peak=$initial;$maxDd=0.0;$trades=[];$position=null;$curve=[];
        $count=count($candles);$slip=$slippageBps/10000;
        for($j=1;$j<$count;$j++) if ((int)$candles[$j]['open_ms']-(int)$candles[$j-1]['open_ms']!==3600000)
            throw new InvalidArgumentException('Non-continuous backtest history');
        for($i=200;$i<$count;$i++) {
            $bar=$candles[$i];
            if ($position) {
                $position['bars']++;
                $stopHit=(float)$bar['low']<=$position['stop'];$targetHit=(float)$bar['high']>=$position['target'];
                $exit=null;$why=null;
                if ($stopHit) {$exit=min($position['stop'],(float)$bar['open'])*(1-$slip);$why='stop';}
                elseif ($targetHit) {$exit=$position['target']*(1-$slip);$why='target';}
                elseif ($position['bars']>=6) {$exit=(float)$bar['close']*(1-$slip);$why='time';}
                if ($exit!==null) {
                    $gross=$position['quantity']*($exit-$position['entry']);
                    $fees=$position['quantity']*($position['entry']+$exit)*$feeRate;
                    $pnl=$gross-$fees;$equity+=$pnl;
                    $trades[]=['entry_index'=>$position['index'],'exit_index'=>$i,'entry'=>$position['entry'],
                        'exit'=>$exit,'quantity'=>$position['quantity'],'pnl'=>$pnl,'exit_reason'=>$why];
                    $position=null;$peak=max($peak,$equity);$maxDd=max($maxDd,1-$equity/$peak);
                }
            }
            // Use candles ending at i-1 to decide whether to enter at bar i open.
            if (!$position && $i>200 && $equity>0) {
                $window=array_slice($candles,max(0,$i-251),min(251,$i));
                if (count($window)>=200) {
                    try {$f=$this->features->calculate($window);$signal=$this->strategy->signal($f,$parameters);}
                    catch (InvalidArgumentException) {$signal=['entry'=>false];}
                    if ($signal['entry']) {
                        $entry=(float)$bar['open']*(1+$slip);
                        $stop=$entry-$signal['stop_distance'];
                        $quantity=min($equity*.005/$signal['stop_distance'],$equity*.25/$entry);
                        $quantity=min($quantity,max(0,$equity*.50/$entry));
                        // Signal-derived stop and target are anchored to the actual next-bar entry.
                        if ($quantity>0 && $stop>0) $position=['index'=>$i,'entry'=>$entry,'quantity'=>$quantity,
                            'stop'=>$stop,'target'=>$entry+($signal['reward_risk']??2.0)*$signal['stop_distance'],'bars'=>0];
                    }
                }
            }
            $curve[]=['index'=>$i,'realized_equity'=>$equity];
        }
        // Open positions are explicitly marked, never treated as closed wins.
        $wins=count(array_filter($trades,fn($t)=>$t['pnl']>0));
        return ['strategy'=>MomentumBreakout::VERSION,'initial_equity'=>$initial,'final_realized_equity'=>$equity,
            'realized_return_pct'=>100*($equity/$initial-1),'max_realized_drawdown_pct'=>100*$maxDd,
            'trade_count'=>count($trades),'win_rate_pct'=>count($trades)?100*$wins/count($trades):null,
            'open_position'=>$position,'trades'=>$trades,'equity_curve'=>$curve,
            'assumptions'=>['fee_rate'=>$feeRate,'slippage_bps'=>$slippageBps,'intrabar_ambiguity'=>'stop_first',
                'fill'=>'next_hour_open','spread_history'=>'not_available','candidate_parameters'=>$parameters]];
    }
}
