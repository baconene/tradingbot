<?php
namespace App\Research;

/** Completed-candle structure preview; never authorizes exchange orders. */
final class MarketStructureSignal
{
    public function analyze(array $candles, int $lookback=20, float $rr=2.0): array
    {
        if ($lookback<5 || $lookback>100 || $rr<0.5 || $rr>10 || count($candles)<$lookback+2)
            return ['state'=>'insufficient_data','direction'=>'none','entry'=>null,'stop'=>null,'target'=>null];
        $last=$candles[count($candles)-1];
        $previous=array_slice($candles,-$lookback-1,$lookback);
        $priorHigh=max(array_column($previous,'high'));
        $priorLow=min(array_column($previous,'low'));
        $prevHalf=array_slice($previous,0,intdiv($lookback,2));
        $recentHalf=array_slice($previous,intdiv($lookback,2));
        $higherHigh=max(array_column($recentHalf,'high'))>max(array_column($prevHalf,'high'));
        $higherLow=min(array_column($recentHalf,'low'))>min(array_column($prevHalf,'low'));
        $lowerHigh=max(array_column($recentHalf,'high'))<max(array_column($prevHalf,'high'));
        $lowerLow=min(array_column($recentHalf,'low'))<min(array_column($prevHalf,'low'));
        $direction='none';$state='waiting';
        if($higherHigh&&$higherLow){$direction='long';$state=(float)$last['close']>$priorHigh?'confirmed':'waiting';}
        elseif($lowerHigh&&$lowerLow){$direction='short';$state=(float)$last['close']<$priorLow?'confirmed':'waiting';}
        $entry=$state==='confirmed'?(float)$last['close']:null;
        $stop=$direction==='long'?$priorLow:($direction==='short'?$priorHigh:null);
        $risk=$entry!==null&&$stop!==null?abs($entry-$stop):null;
        if($risk===null||$risk<=0||($direction==='long'&&$entry<=$stop)||($direction==='short'&&$entry>=$stop)){
            $state='waiting';$entry=null;$risk=null;
        }
        return ['state'=>$state,'direction'=>$direction,'entry'=>$entry,'stop'=>$stop,
            'target'=>$risk===null?null:($direction==='long'?$entry+$risk*$rr:$entry-$risk*$rr),
            'rr'=>$rr,'previous_high'=>$priorHigh,'previous_low'=>$priorLow,
            'higher_high'=>$higherHigh,'higher_low'=>$higherLow,'lower_high'=>$lowerHigh,'lower_low'=>$lowerLow,
            'signal_time'=>$last['open_ms']??null,'method'=>'completed_candle_structure_v1',
            'execution_enabled'=>false,'probability'=>null,
            'note'=>'Exploratory structure signal on spot candles, not futures-validated or model-calibrated'];
    }
}
