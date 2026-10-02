<?php
namespace App\Strategies;
final class MomentumBreakout {
    public const VERSION='MBR-001-v1';
    /** A completed hourly signal candle only; prior 20 high excludes the signal candle. */
    public function signal(array $f): array {
        foreach (['close','ema20','ema50','rsi14','atr14','previous_20_high','relative_volume'] as $key)
            if (!isset($f[$key]) || !is_numeric($f[$key]) || !is_finite((float)$f[$key])) return ['entry'=>false,'reason'=>'missing_'.$key];
        if ($f['atr14']<=0 || $f['close']<=0) return ['entry'=>false,'reason'=>'invalid_market'];
        $passes=$f['close']>$f['previous_20_high'] && $f['ema20']>$f['ema50']
            && $f['rsi14']>=55 && $f['rsi14']<=75 && $f['relative_volume']>=1.5;
        if (!$passes) return ['entry'=>false,'reason'=>'conditions_not_met'];
        $stopDistance=1.5*$f['atr14'];
        return ['entry'=>true,'reason'=>'breakout','stop_distance'=>$stopDistance,
            'stop'=>$f['close']-$stopDistance,'target'=>$f['close']+2*$stopDistance,
            'strategy_version'=>self::VERSION];
    }
}
