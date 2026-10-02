<?php
namespace App\Console\Commands;

use App\Backtesting\HourlyBacktester;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class WalkForwardBacktest extends Command
{
    protected $signature='astra:walk-forward {--start=} {--end=}';
    protected $description='Four-fold chronological research-only walk-forward; never promotes or executes trades';

    public function handle(HourlyBacktester $engine): int
    {
        $query=DB::table('market_candles')->where('symbol','BTCUSDT')->where('interval','1h');
        if($this->option('start'))$query->where('open_time','>=',$this->option('start'));
        if($this->option('end'))$query->where('open_time','<',$this->option('end'));
        $rows=$query->orderBy('open_time')->get();
        if($rows->count()<3000){$this->error('At least 3,000 continuous completed hourly candles required.');return self::FAILURE;}
        $candles=$rows->map(fn($r)=>['open_ms'=>CarbonImmutable::parse($r->open_time,'UTC')->getTimestampMs(),
            'open'=>(float)$r->open,'high'=>(float)$r->high,'low'=>(float)$r->low,
            'close'=>(float)$r->close,'volume'=>(float)$r->volume])->all();
        foreach($candles as $i=>$c)if($i>0 && $c['open_ms']-$candles[$i-1]['open_ms']!==3600000){
            $this->error('Discontinuous history; refusing walk-forward.');return self::FAILURE;
        }
        $n=count($candles);$folds=[];$candidateSets=[];
        // Hypotheses fixed in source before examining test folds. Training selection uses return
        // subject to positive profit, <=10% drawdown and >=10 closed trades.
        foreach([0.0,0.15,0.3] as $minBreakout)
        foreach([1.0,2.0,INF] as $maxRange)
            $candidateSets[]=['min_breakout_atr'=>$minBreakout,'max_signal_range_atr'=>$maxRange];
        for($fold=0;$fold<4;$fold++){
            $trainEnd=(int)floor($n*(.5+$fold*.1));
            $testEnd=$fold===3?$n:(int)floor($n*(.6+$fold*.1));
            $train=array_slice($candles,0,$trainEnd);
            $ranked=[];
            foreach($candidateSets as $params){
                $report=$engine->run($train,1000,.001,5,$params);
                $eligible=$report['trade_count']>=10 && $report['realized_return_pct']>0 && $report['max_realized_drawdown_pct']<=10;
                $ranked[]=['params'=>$params,'eligible'=>$eligible,'return'=>$report['realized_return_pct'],
                    'drawdown'=>$report['max_realized_drawdown_pct'],'trades'=>$report['trade_count']];
            }
            usort($ranked,fn($a,$b)=>($b['eligible']<=>$a['eligible'])?:($b['return']<=>$a['return']));
            $chosen=$ranked[0];
            $slice=array_slice($candles,$trainEnd-251,$testEnd-($trainEnd-251));
            try{$test=$engine->run($slice,1000,.001,5,$chosen['params']);$baseline=$engine->run($slice);}
            catch(InvalidArgumentException $e){$this->error($e->getMessage());return self::FAILURE;}
            $summarize=static fn(array $r)=>['trades'=>$r['trade_count'],'win_rate_pct'=>$r['win_rate_pct'],
                'return_pct'=>$r['realized_return_pct'],'max_drawdown_pct'=>$r['max_realized_drawdown_pct'],
                'profit_factor'=>$r['profit_factor'],'sharpe'=>$r['annualized_hourly_realized_sharpe'],
                'exit_breakdown'=>$r['exit_breakdown']];
            $folds[]=['fold'=>$fold+1,'train_end'=>$rows[$trainEnd-1]->close_time,
                'test_start'=>$rows[$trainEnd]->open_time,'test_end'=>$rows[$testEnd-1]->close_time,
                'selection_eligible'=>$chosen['eligible'],'selected_parameters'=>$chosen['params'],
                'training_candidates'=>$ranked,'test'=>$summarize($test),'baseline'=>$summarize($baseline)];
        }
        $result=['mode'=>'research_only','promoted'=>false,'folds'=>$folds,
            'caution'=>'Previously inspected data: diagnostic walk-forward only, not independent untouched validation'];
        DB::table('backtest_runs')->insert(['strategy_version'=>'MBR-001-walk-forward-research',
            'data_start'=>$rows->first()->open_time,'data_end'=>$rows->last()->close_time,
            'data_hash'=>hash('sha256',json_encode($candles,JSON_THROW_ON_ERROR)),
            'results'=>json_encode($result,JSON_THROW_ON_ERROR),'created_at'=>now(),'updated_at'=>now()]);
        $this->line(json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));return self::SUCCESS;
    }
}
