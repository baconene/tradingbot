<?php
namespace App\Prediction;

use InvalidArgumentException;

final class FrozenModelValidator
{
    public function validate(array $model,array $trades): array
    {
        if(($model['model_version']??null)!==HistoricalProbabilityResearch::VERSION
            || !isset($model['training_bucket_counts'],$model['training_prior']))
            throw new InvalidArgumentException('Missing frozen training model.');
        $prior=(float)$model['training_prior'];
        if($prior<=0||$prior>=1)throw new InvalidArgumentException('Invalid frozen prior.');
        $n=0;$wins=0;$brier=0.0;$baseline=0.0;$absError=0.0;$bins=[];
        foreach($trades as $trade){
            $c=$trade['entry_context']??[];$rsi=$c['rsi14']??null;$volume=$c['relative_volume']??null;
            if(!is_numeric($rsi)||!is_numeric($volume)||!is_finite((float)$rsi)||!is_finite((float)$volume)
                ||$rsi<0||$rsi>100||$volume<0||!isset($trade['pnl'])||!is_numeric($trade['pnl']))continue;
            $key=($rsi<60?'rsi_low':'rsi_high').'|'.($volume<2?'volume_low':'volume_high');
            $bucket=$model['training_bucket_counts'][$key]??['n'=>0,'wins'=>0];
            $p=($bucket['wins']+20*$prior)/($bucket['n']+20);
            $won=(float)$trade['pnl']>0?1:0;
            $n++;$wins+=$won;$brier+=($p-$won)**2;$baseline+=($prior-$won)**2;
            $group=(string)min(4,(int)floor($p*5));
            $bins[$group]??=['count'=>0,'predicted_sum'=>0.0,'wins'=>0];
            $bins[$group]['count']++;$bins[$group]['predicted_sum']+=$p;$bins[$group]['wins']+=$won;
        }
        if($n<40)throw new InvalidArgumentException('At least 40 independently later closed trades with valid features required.');
        $calibration=[];
        foreach($bins as $group=>$bin){
            $predicted=$bin['predicted_sum']/$bin['count'];$observed=$bin['wins']/$bin['count'];
            $absError+=$bin['count']*abs($predicted-$observed);
            $calibration[]=['bin'=>$group,'count'=>$bin['count'],'predicted_rate'=>$predicted,'observed_rate'=>$observed];
        }
        $score=$brier/$n;$baselineScore=$baseline/$n;$mae=$absError/$n;
        $criteria=['minimum_40_trades'=>$n>=40,'beats_frozen_training_prior'=>$score<$baselineScore,
            'calibration_mae_at_most_0_10'=>$mae<=.10];
        return ['trade_count'=>$n,'win_rate'=>$wins/$n,'brier_score'=>$score,
            'baseline_brier_score'=>$baselineScore,'calibration_mae'=>$mae,
            'calibration_bins'=>$calibration,'criteria'=>$criteria,
            'passed'=>!in_array(false,$criteria,true),'status'=>'independent_validation_candidate',
            'note'=>'Different later backtest is required; source independence beyond timestamp/hash cannot be proven automatically.'];
    }
}
