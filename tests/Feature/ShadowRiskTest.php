<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ShadowRiskTest extends TestCase
{
    use RefreshDatabase;

    public function test_hypothetical_risk_evaluation_never_enables_execution(): void
    {
        $input=['equity'=>1000,'daily_reference'=>1000,'high_water'=>1000,
            'daily_pnl'=>0,'open_positions'=>0,'exposure'=>0,
            'entry'=>100,'stop'=>98,'quantity'=>1.2,'min_notional'=>5,'step_size'=>0.1,'min_qty'=>0.1];
        $this->postJson('/api/risk/shadow-evaluate',$input)->assertOk()
            ->assertJsonPath('execution_enabled',false)
            ->assertJsonPath('decision.reason','manual_approval_required');
        $this->postJson('/api/risk/shadow-evaluate',$input+['manual_approved'=>true])
            ->assertOk()->assertJsonPath('decision.allowed',true);
        $this->postJson('/api/risk/shadow-evaluate',array_merge($input,['daily_pnl'=>-20,'manual_approved'=>true]))
            ->assertOk()->assertJsonPath('decision.reason','daily_loss_limit');
        $this->get('/api/risk/status')->assertOk()->assertJsonPath('execution_enabled',false);
    }

    public function test_invalid_or_missing_exchange_filters_fail_validation(): void
    {
        $this->postJson('/api/risk/shadow-evaluate',['equity'=>1000])->assertUnprocessable();
    }
}
