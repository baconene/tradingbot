<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ReviewPredictionModel extends Command
{
    protected $signature='astra:review-prediction {model_id} {action : approve or revoke} {--confirm}';
    protected $description='Explicit operator review for paper-observation only; never authorizes trading';
    public function handle(): int
    {
        $id=(int)$this->argument('model_id');$action=$this->argument('action');
        if(!in_array($action,['approve','revoke'],true)||!$this->option('confirm')){
            $this->error('Specify approve or revoke and --confirm.');return self::FAILURE;
        }
        $model=DB::table('model_versions')->where('id',$id)->first();
        if(!$model||!str_starts_with($model->version,'M5-binned-v1-')){
            $this->error('Research model not found.');return self::FAILURE;
        }
        $metrics=json_decode($model->metrics??'{}',true);
        if($action==='approve'){
            $v=$metrics['independent_validation']??null;
            if(!$v||($v['passed']??false)!==true||!isset($v['backtest_id'])){
                $this->error('Independent later validation must pass before paper-observation approval.');
                return self::FAILURE;
            }
            $backtest=DB::table('backtest_runs')->where('id',$v['backtest_id'])->first();
            if(!$backtest||$backtest->data_hash!==$v['data_hash']){
                $this->error('Validation evidence changed or is missing.');return self::FAILURE;
            }
        }
        $metrics['review_history'][]=['action'=>$action,'at'=>now()->toIso8601String(),
            'scope'=>'paper_observation_only'];
        DB::table('model_versions')->where('id',$id)->update([
            'status'=>$action==='approve'?'paper_approved':'revoked',
            'approved_at'=>$action==='approve'?now():null,
            'metrics'=>json_encode($metrics,JSON_THROW_ON_ERROR),'updated_at'=>now()
        ]);
        $this->info('Model '.$action.'d for paper observation only. Exchange execution remains locked.');
        return self::SUCCESS;
    }
}
