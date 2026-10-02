<?php
namespace App\Strategies;
final class MomentumBreakout {
    public const VERSION='MBR-001-v1';
    /** Candidate overrides are research-only; the deployed default remains unchanged. */
    public function signal(array $f,array $params=[]): array {
        $p=array_replace(['rsi_min'=>55.0,'rsi_max'=>75.0,'relative_volume_min'=>1.5,'atr_stop'=>1.5,'reward_risk'=>2.0],$params);
        foreach (['close','ema20','ema50','rsi14','atr14','previous_20_high','relative_volume'] as $key)
            if (!isset($f[$key]) || !is_numeric($f[$key]) || !is_finite((float)$f[$key])) return ['entry'=>false,'reason'=>'missing_'.$key];
        if ($f['atr14']<=0 || $f['close']<=0) return ['entry'=>false,'reason'=>'invalid_market'];
        $passes=$f['close']>$f['previous_20_high'] && $f['ema20']>$f['ema50']
            && $f['rsi14']>=$p['rsi_min'] && $f['rsi14']<=$p['rsi_max']
            && $f['relative_volume']>=$p['relative_volume_min'];
        if (!$passes) return ['entry'=>false,'reason'=>'conditions_not_met'];
        $distance=$p['atr_stop']*$f['atr14'];
        return ['entry'=>true,'reason'=>'breakout','stop_distance'=>$distance,
            'stop'=>$f['close']-$distance,'target'=>$f['close']+$p['reward_risk']*$distance,
            'reward_risk'=>$p['reward_risk'],'strategy_version'=>self::VERSION];
    }
}
