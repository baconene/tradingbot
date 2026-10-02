<?php
namespace App\Prediction;

use InvalidArgumentException;

/**
 * Offline, deterministic, Laplace-smoothed RSI/relative-volume probability bins.
 * Label is closed-trade net profitability, NOT target-before-stop probability.
 * No model is approved for execution.
 */
final class HistoricalProbabilityResearch
{
    public const VERSION='M5-binned-v1';
    private function bucket(array $context): ?string
    {
        $rsi=$context['rsi14']??null;$volume=$context['relative_volume']??null;
        if(!is_numeric($rsi)||!is_numeric($volume)||!is_finite((float)$rsi)||!is_finite((float)$volume)
            ||$rsi<0||$rsi>100||$volume<0) return null;
        return ($rsi<60?'rsi_low':'rsi_high').'|'.($volume<2?'volume_low':'volume_high');
    }
    public function evaluate(array $trades): array
    {
        $records=[];
        foreach($trades as $trade){
            $key=$this->bucket($trade['entry_context']??[]);
            if($key===null||!isset($trade['pnl'])||!is_numeric($trade['pnl']))continue;
            $records[]=['bucket'=>$key,'won'=>(float)$trade['pnl']>0?1:0,
                'entry_index'=>$trade['entry_index']??null];
        }
        if(count($records)<60)throw new InvalidArgumentException('At least 60 trades with valid entry-time indicators required.');
        $split=(int)floor(count($records)*.75);
        $train=array_slice($records,0,$split);$holdout=array_slice($records,$split);
        $counts=[];$trainWins=0;
        foreach($train as $record){$key=$record['bucket'];$counts[$key]??=['n'=>0,'wins'=>0];
            $counts[$key]['n']++;$counts[$key]['wins']+=$record['won'];$trainWins+=$record['won'];}
        $prior=($trainWins+1)/(count($train)+2);$brier=0.0;$baselineBrier=0.0;
        $bins=[];$observed=[];
        foreach($holdout as $record){
            $bucket=$counts[$record['bucket']]??['n'=>0,'wins'=>0];
            // 20-observation shrinkage toward the training-only prior.
            $p=($bucket['wins']+20*$prior)/($bucket['n']+20);
            $brier+=($p-$record['won'])**2;$baselineBrier+=($prior-$record['won'])**2;
            $group=(string)min(4,(int)floor($p*5));
            $bins[$group]??=['count'=>0,'predicted_sum'=>0.0,'wins'=>0];
            $bins[$group]['count']++;$bins[$group]['predicted_sum']+=$p;$bins[$group]['wins']+=$record['won'];
            $observed[]=['entry_index'=>$record['entry_index'],'probability'=>$p,'outcome'=>$record['won']];
        }
        $calibration=[];
        ksort($bins);
        foreach($bins as $bin=>$values)$calibration[]=['bin'=>$bin,'count'=>$values['count'],
            'predicted_rate'=>$values['predicted_sum']/$values['count'],
            'observed_rate'=>$values['wins']/$values['count']];
        return ['model_version'=>self::VERSION,'label'=>'net_profitable_closed_trade',
            'feature_names'=>['rsi14','relative_volume'],'training_trades'=>count($train),
            'holdout_trades'=>count($holdout),'train_win_rate'=>$trainWins/count($train),
            'holdout_win_rate'=>array_sum(array_column($holdout,'won'))/count($holdout),
            'training_prior'=>$prior,'brier_score'=>$brier/count($holdout),
            'baseline_brier_score'=>$baselineBrier/count($holdout),
            'calibration_bins'=>$calibration,'training_bucket_counts'=>$counts,
            'holdout_predictions'=>$observed,'status'=>'observation','approved'=>false,
            'limitations'=>['Single chronological holdout; source strategy and historical data already inspected',
                'Closed-trade profitability label is not target-before-stop',
                'Small bins and regime shifts can invalidate calibration',
                'Not eligible for trade entry or automatic promotion']];
    }
}
