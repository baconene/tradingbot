<?php
namespace App\Console\Commands;

use App\Prediction\FrozenModelValidator;
use App\Strategies\MomentumBreakout;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ValidatePredictionResearch extends Command
{
    protected $signature='astra:validate-prediction {model_id} {backtest_id}';
    protected $description='Evaluate a frozen model on a separate, later backtest; never executes orders';
    public function handle(FrozenModelValidator $validator): int
    {
        $model=DB::table('model_versions')->where('id',(int)$this->argument('model_id'))->first();
        $run=DB::table('backtest_runs')->where('id',(int)$this->argument('backtest_id'))
            ->where('strategy_version',MomentumBreakout::VERSION)->first();
        if(!$model||!$run||!str_starts_with($model->version,'M5-binned-v1-')){
            $this->error('Model or standard backtest not found.');return self::FAILURE;
        }
        $metrics=json_decode($model->metrics??'{}',true);
        $source=DB::table('backtest_runs')->where('id',$metrics['source_backtest_id']??0)->first();
        if(!$source||$source->data_hash===$run->data_hash
            ||strtotime($run->data_start)<=strtotime($source->data_end)){
            $this->error('Validation requires a different dataset starting strictly after source backtest ends.');
            return self::FAILURE;
        }
        $report=json_decode($run->results,true);
        if(($report['assumptions']['model_version']??null)!=='ohlc-v2'){
            $this->error('Validation backtest must use corrected ohlc-v2 execution assumptions.');return self::FAILURE;
        }
        try{$result=$validator->validate($metrics,$report['trades']??[]);}
        catch(InvalidArgumentException $e){$this->error($e->getMessage());return self::FAILURE;}
        $result['backtest_id']=$run->id;$result['data_hash']=$run->data_hash;
        $result['data_start']=$run->data_start;$result['data_end']=$run->data_end;
        $result['evaluated_at']=now()->toIso8601String();
        // Any new validation revokes prior approval pending fresh explicit review.
        $metrics['independent_validation']=$result;
        DB::table('model_versions')->where('id',$model->id)->update([
            'status'=>'observation','approved_at'=>null,'metrics'=>json_encode($metrics,JSON_THROW_ON_ERROR),
            'updated_at'=>now()
        ]);
        $this->line(json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
