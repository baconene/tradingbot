<?php
namespace App\Support;
use DomainException;
final class TradingPolicy {
    public const STARTING_CAPITAL = 1000.0;
    public const RISK_PER_TRADE = 0.005;
    public const DAILY_LOSS_LIMIT = 0.02;
    public const DRAWDOWN_LIMIT = 0.10;
    public const MAX_POSITION_NOTIONAL = 0.25;
    public const MAX_PORTFOLIO_EXPOSURE = 0.50;
    public const MAX_OPEN_POSITIONS = 2;
    public const MANUAL_APPROVAL_USDT = 100.0;

    public function summary(): array {
        return ['riskPerTrade' => self::RISK_PER_TRADE, 'dailyLossLimit' => self::DAILY_LOSS_LIMIT,
            'drawdownLimit' => self::DRAWDOWN_LIMIT, 'maxPositionNotional' => self::MAX_POSITION_NOTIONAL,
            'maxPortfolioExposure' => self::MAX_PORTFOLIO_EXPOSURE, 'manualApprovalUsdt' => self::MANUAL_APPROVAL_USDT,
            'killSwitch' => 'locked', 'tradingEnabled' => false];
    }
    public function assertCanSubmitOrder(): never {
        throw new DomainException('Execution is disabled in Milestone 1. No exchange orders may be submitted.');
    }
}
