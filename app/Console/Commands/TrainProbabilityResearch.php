<?php
namespace App\Console\Commands;

use App\Prediction\HistoricalProbabilityResearch;
use App\Strategies\MomentumBreakout;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class TrainProbabilityResearch extends Command
{
    protected $signature='astra:train-prediction {--backtest-id=}';
    protected $description='Offline M5 probability research on chronological backtest trade outcomes; no execution';
    public function handle(HistoricalProbabilityResearch $model): int
    {
        $query=DB::table('backtest_runs')->where('strategy_version',MomentumBreakout::VERSION);
        if($this->option('backtest-id'))$query->where('id',(int)$this->option('backtest-id'));
        $run=$query->orderByDesc('id')->first();
        if(!$run){$this->error('No standard backtest found. Run astra:backtest first.');return self::FAILURE;}
        $report=json_decode($run->results,true);
        if(($report['assumptions']['model_version']??null)!=='ohlc-v2'){
            $this->error('Requires a fresh ohlc-v2 backtest with corrected entry-bar fills.');return self::FAILURE;
        }
        try{$result=$model->evaluate($report['trades']??[]);}
        catch(InvalidArgumentException $e){$this->error($e->getMessage());return self::FAILURE;}
        $result['source_backtest_id']=$run->id;$result['source_data_hash']=$run->data_hash;
        $result['source_strategy']=$run->strategy_version;
        // Immutable version per experiment; never approved or made eligible for live signals.
        $version=HistoricalProbabilityResearch::VERSION.'-backtest-'.$run->id.'-'.now()->format('YmdHis');
        DB::table('model_versions')->insert(['version'=>$version,'status'=>'observation',
            'metrics'=>json_encode($result,JSON_THROW_ON_ERROR),'approved_at'=>null,
            'created_at'=>now(),'updated_at'=>now()]);
        $this->line(json_encode(array_diff_key($result,['holdout_predictions'=>true]),JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
