<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ModelReviewTest extends TestCase
{
    use RefreshDatabase;
    private function model(array $validation=[]): int
    {
        return DB::table('model_versions')->insertGetId([
            'version'=>'M5-binned-v1-review-'.uniqid(),'status'=>'observation',
            'metrics'=>json_encode(['independent_validation'=>$validation]),
            'approved_at'=>null,'created_at'=>now(),'updated_at'=>now()]);
    }
    public function test_approval_requires_independent_validation_and_explicit_confirmation(): void
    {
        $id=$this->model();
        $this->assertSame(1,Artisan::call('astra:review-prediction',['model_id'=>$id,'action'=>'approve','--confirm'=>true]));
        $this->assertSame('observation',DB::table('model_versions')->where('id',$id)->value('status'));
        $this->assertSame(1,Artisan::call('astra:review-prediction',['model_id'=>$id,'action'=>'approve']));
        $this->assertSame(0,Artisan::call('astra:review-prediction',['model_id'=>$id,'action'=>'revoke','--confirm'=>true]));
        $this->assertSame('revoked',DB::table('model_versions')->where('id',$id)->value('status'));
    }
    public function test_approved_paper_model_never_enables_execution(): void
    {
        $backtest=DB::table('backtest_runs')->insertGetId([
            'strategy_version'=>'MBR-001-v1','data_start'=>now()->subDays(10),
            'data_end'=>now()->subDays(1),'data_hash'=>str_repeat('a',64),
            'results'=>'{}','created_at'=>now(),'updated_at'=>now()]);
        $id=$this->model(['passed'=>true,'backtest_id'=>$backtest,'data_hash'=>str_repeat('a',64)]);
        $this->assertSame(0,Artisan::call('astra:review-prediction',['model_id'=>$id,'action'=>'approve','--confirm'=>true]));
        $this->assertSame('paper_approved',DB::table('model_versions')->where('id',$id)->value('status'));
        $this->get('/api/research/predictions')->assertOk()->assertJsonPath('execution_enabled',false)
            ->assertJsonPath('runs.0.status','paper_approved');
        $this->get('/api/risk/status')->assertOk()->assertJsonPath('execution_enabled',false);
    }
}
