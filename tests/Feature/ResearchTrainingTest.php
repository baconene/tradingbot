<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

final class ResearchTrainingTest extends TestCase
{
    use RefreshDatabase;

    public function test_training_requires_operator_token_and_never_enables_execution(): void
    {
        Cache::forget('astra:research:training:lock');
        $this->withoutMiddleware(ThrottleRequests::class);
        config()->set('astra.research_training_token','operator-secret');
        config()->set('queue.default','database');
        Queue::fake();
        $this->postJson('/api/research/training')->assertUnauthorized();
        $this->withToken('incorrect')->postJson('/api/research/training')->assertUnauthorized();
        $this->withToken('operator-secret')->postJson('/api/research/training')->assertAccepted()
            ->assertJsonPath('execution_enabled',false);
        Queue::assertPushed(\App\Jobs\RunResearchTraining::class,1);
        $this->withToken('operator-secret')->postJson('/api/research/training')->assertStatus(409);
        Cache::forget('astra:research:training:lock');
        Cache::forget('astra:research:training:status');
    }

    public function test_sync_queue_is_refused_and_history_is_read_only(): void
    {
        Cache::forget('astra:research:training:lock');
        config()->set('astra.research_training_token','operator-secret');
        config()->set('queue.default','sync');
        $this->withToken('operator-secret')->postJson('/api/research/training')->assertStatus(503);
        $this->get('/api/research/training')->assertOk()->assertJsonPath('execution_enabled',false)
            ->assertJsonPath('runs',[]);
    }
    public function test_training_history_includes_dataset_hash_and_fold_win_rates(): void
    {
        DB::table('backtest_runs')->insert([
            'strategy_version'=>'MBR-001-walk-forward-research',
            'data_start'=>'2025-01-01 00:00:00','data_end'=>'2025-12-31 23:59:59',
            'data_hash'=>str_repeat('b',64),
            'results'=>json_encode(['mode'=>'research_only','folds'=>[[
                'fold'=>1,'selection_eligible'=>false,
                'test'=>['trades'=>0,'win_rate_pct'=>null,'return_pct'=>0.0],
                'baseline'=>['trades'=>12,'win_rate_pct'=>25.0,'return_pct'=>-2.0]
            ]]]),
            'created_at'=>now(),'updated_at'=>now()
        ]);
        $this->get('/api/research/training')->assertOk()
            ->assertJsonPath('runs.0.data_hash',str_repeat('b',64))
            ->assertJsonPath('runs.0.results.folds.0.test.trades',0)
            ->assertJsonPath('runs.0.results.folds.0.selection_eligible',false);
    }

}
