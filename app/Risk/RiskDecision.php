<?php
namespace App\Risk;
final class RiskDecision {
    /** Pure deterministic pre-trade checks. Broker filters are mandatory inputs, never guessed. */
    public function evaluate(array $state,array $order): array {
        foreach (['equity','daily_reference','high_water','daily_pnl','open_positions','exposure'] as $k)
            if (!isset($state[$k]) || !is_numeric($state[$k])) return ['allowed'=>false,'reason'=>'missing_state_'.$k];
        if (($state['kill_latched']??true) || ($state['reconciled']??false)!==true) return ['allowed'=>false,'reason'=>'locked_or_unreconciled'];
        if ($state['equity']<=0 || $state['daily_reference']<=0 || $state['high_water']<=0) return ['allowed'=>false,'reason'=>'invalid_equity'];
        if ($state['daily_pnl']<=-$state['daily_reference']*.02) return ['allowed'=>false,'reason'=>'daily_loss_limit'];
        if ($state['equity']<=$state['high_water']*.90) return ['allowed'=>false,'reason'=>'drawdown_kill_required'];
        if ($state['open_positions']>=2) return ['allowed'=>false,'reason'=>'position_limit'];
        foreach (['entry','stop','quantity','min_notional','step_size','min_qty'] as $k)
            if (!isset($order[$k]) || !is_numeric($order[$k]) || $order[$k]<=0) return ['allowed'=>false,'reason'=>'invalid_order_'.$k];
        $entry=(float)$order['entry'];$stop=(float)$order['stop'];$qty=(float)$order['quantity'];$notional=$entry*$qty;
        if ($stop>=$entry) return ['allowed'=>false,'reason'=>'invalid_stop'];
        if ($qty+1e-12<$order['min_qty'] || $notional+1e-8<$order['min_notional']) return ['allowed'=>false,'reason'=>'exchange_minimum'];
        $steps=$qty/$order['step_size'];if (abs($steps-round($steps))>1e-6) return ['allowed'=>false,'reason'=>'lot_size'];
        if ($qty*($entry-$stop)>$state['equity']*.005+1e-8) return ['allowed'=>false,'reason'=>'risk_limit'];
        if ($notional>$state['equity']*.25+1e-8) return ['allowed'=>false,'reason'=>'position_notional'];
        if ($notional+$state['exposure']>$state['equity']*.50+1e-8) return ['allowed'=>false,'reason'=>'portfolio_exposure'];
        if ($notional>100 && ($order['manual_approved']??false)!==true) return ['allowed'=>false,'reason'=>'manual_approval_required'];
        return ['allowed'=>true,'reason'=>'approved','notional'=>$notional,'risk_usdt'=>$qty*($entry-$stop)];
    }
}
