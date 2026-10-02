<?php
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
use App\Support\TradingPolicy;
use DomainException;
final class TradingPolicyTest extends TestCase {
    public function test_risk_policy_defaults_match_approved_specification(): void {
        $policy = new TradingPolicy(); $risk = $policy->summary();
        $this->assertSame(0.005, $risk['riskPerTrade']);
        $this->assertSame(0.02, $risk['dailyLossLimit']);
        $this->assertSame(0.10, $risk['drawdownLimit']);
        $this->assertSame(0.25, $risk['maxPositionNotional']);
        $this->assertSame(0.50, $risk['maxPortfolioExposure']);
        $this->assertSame(100.0, $risk['manualApprovalUsdt']);
        $this->assertFalse($risk['tradingEnabled']);
        $this->assertSame('locked', $risk['killSwitch']);
    }
    public function test_execution_is_unconditionally_disabled(): void {
        $this->expectException(DomainException::class);
        (new TradingPolicy())->assertCanSubmitOrder();
    }
}
