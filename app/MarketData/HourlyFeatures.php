<?php
namespace App\MarketData;
use InvalidArgumentException;
final class HourlyFeatures {
    public const VERSION='hourly-v1';
    /** Rows must be chronologically ordered, completed and point-in-time eligible. */
    public function calculate(array $candles): array {
        if (count($candles)<200) throw new InvalidArgumentException('200 continuous completed candles required for stable EMA warmup.');
        $previous=null;
        foreach ($candles as $c) {
            $time=(int)$c['open_ms'];
            if ($previous!==null && $time-$previous!==3600000) throw new InvalidArgumentException('Historical candle gap or duplicate.');
            $previous=$time;
        }
        $closes=array_map(fn($c)=>(float)$c['close'],$candles);
        $highs=array_map(fn($c)=>(float)$c['high'],$candles);
        $lows=array_map(fn($c)=>(float)$c['low'],$candles);
        $volumes=array_map(fn($c)=>(float)$c['volume'],$candles);
        $n=count($candles);$ema20=$this->ema($closes,20);$ema50=$this->ema($closes,50);
        $gains=[];$losses=[];$tr=[];
        for($i=1;$i<$n;$i++) {
            $delta=$closes[$i]-$closes[$i-1];$gains[]=max(0,$delta);$losses[]=max(0,-$delta);
            $tr[]=max($highs[$i]-$lows[$i],abs($highs[$i]-$closes[$i-1]),abs($lows[$i]-$closes[$i-1]));
        }
        $avgGain=array_sum(array_slice($gains,0,14))/14;$avgLoss=array_sum(array_slice($losses,0,14))/14;
        $atr=array_sum(array_slice($tr,0,14))/14;
        for($i=14;$i<count($gains);$i++) {
            $avgGain=($avgGain*13+$gains[$i])/14;$avgLoss=($avgLoss*13+$losses[$i])/14;
            $atr=($atr*13+$tr[$i])/14;
        }
        $rsi=$avgLoss==0 ? ($avgGain==0 ? 50.0 : 100.0) : 100-100/(1+$avgGain/$avgLoss);
        $priorVolumes=array_slice($volumes,-21,20);
        $avgVolume=array_sum($priorVolumes)/20;
        return [
            'close'=>$closes[$n-1], 'ema20'=>$ema20,'ema50'=>$ema50,
            'rsi14'=>$rsi,'atr14'=>$atr,
            'previous_20_high'=>max(array_slice($highs,-21,20)),
            'relative_volume'=>$avgVolume>0 ? $volumes[$n-1]/$avgVolume : null,
            'spread_bps'=>null, 'order_book_imbalance'=>null, 'recent_order_flow'=>null,
        ];
    }
    private function ema(array $values,int $period): float {
        $ema=array_sum(array_slice($values,0,$period))/$period;$k=2/($period+1);
        for($i=$period;$i<count($values);$i++) $ema=$values[$i]*$k+$ema*(1-$k);
        return $ema;
    }
}
