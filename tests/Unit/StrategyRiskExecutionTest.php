<?php
namespace Tests\Unit;
use App\Strategies\MomentumBreakout;
use App\Risk\RiskDecision;
use App\Execution\OrderStateMachine;
use App\Execution\BinanceSpotTestnet;
use App\Prediction\ProbabilityGate;
use DomainException;
use PHPUnit\Framework\TestCase;
final class StrategyRiskExecutionTest extends TestCase {
    public function test_breakout_excludes_missing_features_and_requires_all_conditions(): void {
        $s=new MomentumBreakout();$this->assertFalse($s->signal([])['entry']);
        $f=['close'=>110,'ema20'=>108,'ema50'=>105,'rsi14'=>65,'atr14'=>2,'previous_20_high'=>109,'relative_volume'=>1.6];
        $this->assertTrue($s->signal($f)['entry']);$this->assertEquals(107,$s->signal($f)['stop']);
        $f['relative_volume']=1.4;$this->assertFalse($s->signal($f)['entry']);
    }
    public function test_risk_engine_fails_closed_and_enforces_manual_approval(): void {
        $r=new RiskDecision();$s=['equity'=>1000,'daily_reference'=>1000,'high_water'=>1000,'daily_pnl'=>0,'open_positions'=>0,'exposure'=>0,'kill_latched'=>false,'reconciled'=>true];
        $o=['entry'=>100,'stop'=>98,'quantity'=>1.2,'min_notional'=>5,'step_size'=>.1,'min_qty'=>.1];
        $this->assertSame('manual_approval_required',$r->evaluate($s,$o)['reason']);
        $o['manual_approved']=true;$this->assertTrue($r->evaluate($s,$o)['allowed']);
        $s['daily_pnl']=-20;$this->assertSame('daily_loss_limit',$r->evaluate($s,$o)['reason']);
        $s['daily_pnl']=0;$s['kill_latched']=true;$this->assertFalse($r->evaluate($s,$o)['allowed']);
    }
    public function test_uncertain_orders_cannot_be_blindly_retried(): void {
        $m=new OrderStateMachine();$this->assertSame('uncertain',$m->transition('submitting','uncertain'));
        $this->assertFalse($m->retryAllowed('uncertain'));$this->expectException(DomainException::class);$m->transition('uncertain','submitting');
    }
    public function test_exchange_submission_is_hard_locked(): void {
        $this->expectException(DomainException::class);(new BinanceSpotTestnet())->submit([]);
    }
    public function test_prediction_requires_matching_approved_calibrated_model(): void {
        $g=new ProbabilityGate();$p=['model_version'=>'v1','snapshot_hash'=>'abc','probability'=>.7,'calibration_approved'=>false];
        $this->assertFalse($g->eligible($p,'v1','abc')['eligible']);$p['calibration_approved']=true;
        $this->assertTrue($g->eligible($p,'v1','abc')['eligible']);$this->assertFalse($g->eligible($p,'v1','different')['eligible']);
    }
}
