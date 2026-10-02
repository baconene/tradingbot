<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PredictionResearchTest extends TestCase
{
    use RefreshDatabase;
    public function test_prediction_lab_is_read_only_and_never_approves_models(): void
    {
        DB::table('model_versions')->insert(['version'=>'M5-binned-v1-backtest-1-20261003000000',
            'status'=>'observation','metrics'=>json_encode(['training_trades'=>60,'holdout_trades'=>20]),
            'approved_at'=>null,'created_at'=>now(),'updated_at'=>now()]);
        $this->get('/api/research/predictions')->assertOk()
            ->assertJsonPath('execution_enabled',false)
            ->assertJsonPath('model_approval_enabled',false)
            ->assertJsonPath('runs.0.approved',false)
            ->assertJsonPath('runs.0.metrics.holdout_trades',20);
    }
}
