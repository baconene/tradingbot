<?php
namespace App\Console\Commands;

use App\Backtesting\HourlyBacktester;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class OptimizeBacktest extends Command
{
    protected $signature='astra:optimize {--start=} {--end=}';
    protected $description='Bounded research-only parameter sweep with chronological holdout; never deploys strategy changes';

    public function handle(HourlyBacktester $engine): int
    {
        $query=DB::table('market_candles')->where('symbol','BTCUSDT')->where('interval','1h');
        if($this->option('start'))$query->where('open_time','>=',$this->option('start'));
        if($this->option('end'))$query->where('open_time','<',$this->option('end'));
        $rows=$query->orderBy('open_time')->get();
        if($rows->count()<1500){$this->error('At least 1,500 continuous completed hourly candles required.');return self::FAILURE;}
        $candles=$rows->map(fn($r)=>[
            'open_ms'=>CarbonImmutable::parse($r->open_time,'UTC')->getTimestampMs(),
            'open'=>(float)$r->open,'high'=>(float)$r->high,'low'=>(float)$r->low,
            'close'=>(float)$r->close,'volume'=>(float)$r->volume,
        ])->all();
        foreach($candles as $i=>$c){
            if($i>0 && $c['open_ms']-$candles[$i-1]['open_ms']!==3600000){
                $this->error('Discontinuous data; refusing optimization.');return self::FAILURE;
            }
        }
        // Last 25% is an untouched chronological holdout, never used to rank candidates.
        $split=(int)floor(count($candles)*.75);
        $train=array_slice($candles,0,$split);
        $holdout=array_slice($candles,max(0,$split-200)); // exactly 200 warmup candles; first eligible entry is in holdout
        $candidates=[];
        foreach([50.0,55.0,60.0] as $rsi)
        foreach([1.25,1.5,2.0] as $volume)
        foreach([1.5,2.0] as $reward){
            $params=['rsi_min'=>$rsi,'rsi_max'=>75.0,'relative_volume_min'=>$volume,'atr_stop'=>1.5,'reward_risk'=>$reward];
            $report=$engine->run($train,1000,.001,5,$params);
            $trades=$report['trade_count'];
            $candidates[]=['parameters'=>$params,'training'=>[
                'trade_count'=>$trades,'win_rate_pct'=>$report['win_rate_pct'],
                'return_pct'=>$report['realized_return_pct'],
                'max_drawdown_pct'=>$report['max_realized_drawdown_pct'],
            ],'eligible'=>$trades>=10 && $report['realized_return_pct']>0 && $report['max_realized_drawdown_pct']<=10];
        }
        // Rank only training results; do not optimize against holdout.
        usort($candidates,fn($a,$b)=>($b['eligible']<=>$a['eligible'])
            ?:($b['training']['return_pct']<=>$a['training']['return_pct']));
        $best=$candidates[0];
        $test=$engine->run($holdout,1000,.001,5,$best['parameters']);
        $baseline=$engine->run($holdout);
        $result=[
            'mode'=>'research_only','promoted'=>false,'candidate_count'=>count($candidates),
            'train_start'=>$rows->first()->open_time,
            'train_end'=>$rows[$split-1]->close_time,
            'holdout_start'=>$rows[$split]->open_time,
            'holdout_end'=>$rows->last()->close_time,
            'selected_parameters'=>$best['parameters'],
            'selection_eligible'=>$best['eligible'],
            'training'=>$best['training'],
            'holdout'=>self::summary($test),
            'holdout_baseline'=>self::summary($baseline),
            'candidates'=>$candidates,
            'limitations'=>['Single holdout; not walk-forward validation','No historical bid/ask spreads','Do not select another candidate based on holdout results'],
        ];
        DB::table('backtest_runs')->insert([
            'strategy_version'=>'MBR-001-optimization-research',
            'data_start'=>$rows->first()->open_time,'data_end'=>$rows->last()->close_time,
            'data_hash'=>hash('sha256',json_encode($candles,JSON_THROW_ON_ERROR)),
            'results'=>json_encode($result,JSON_THROW_ON_ERROR),
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $this->line(json_encode(array_diff_key($result,['candidates'=>true]),JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
    private static function summary(array $r): array
    {
        return ['trade_count'=>$r['trade_count'],'win_rate_pct'=>$r['win_rate_pct'],
            'realized_return_pct'=>$r['realized_return_pct'],
            'max_realized_drawdown_pct'=>$r['max_realized_drawdown_pct']];
    }
}
