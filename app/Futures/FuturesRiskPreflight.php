<?php
namespace App\Futures;

/**
 * Standalone conservative futures risk preflight; simulation only.
 * Does not use a leverage multiplier to increase permitted stop-risk or notional.
 */
final class FuturesRiskPreflight
{
 public function evaluate(array $state,array $proposal): array
 {
  foreach(['equity','available_margin','maintenance_margin','gross_notional','daily_pnl','daily_reference','high_water'] as $key)
   if(!isset($state[$key])||!is_numeric($state[$key])||!is_finite((float)$state[$key]))
    return ['allowed'=>false,'reason'=>'missing_or_invalid_'.$key];
  if(($state['reconciled']??false)!==true||($state['kill_latched']??true)!==false)
   return ['allowed'=>false,'reason'=>'locked_or_unreconciled'];
  $equity=(float)$state['equity'];
  if($equity<=0||$state['daily_reference']<=0||$state['high_water']<=0)
   return ['allowed'=>false,'reason'=>'invalid_equity'];
  if($state['daily_pnl']<=-$state['daily_reference']*.02)
   return ['allowed'=>false,'reason'=>'daily_loss_limit'];
  if($equity<=$state['high_water']*.9)
   return ['allowed'=>false,'reason'=>'drawdown_limit'];
  foreach(['entry','stop','quantity','leverage','min_notional','step_size','min_qty','estimated_round_trip_fees','estimated_funding'] as $key)
   if(!isset($proposal[$key])||!is_numeric($proposal[$key])||!is_finite((float)$proposal[$key]))
    return ['allowed'=>false,'reason'=>'missing_or_invalid_'.$key];
  $entry=(float)$proposal['entry'];$stop=(float)$proposal['stop'];$qty=(float)$proposal['quantity'];
  $lev=(int)$proposal['leverage'];
  if($lev!=$proposal['leverage']||$lev<1||$lev>100||$entry<=0||$stop<=0||$qty<=0||
   $proposal['min_notional']<=0||$proposal['step_size']<=0||$proposal['min_qty']<=0||
   $proposal['estimated_round_trip_fees']<0||$proposal['estimated_funding']<0)
   return ['allowed'=>false,'reason'=>'invalid_order'];
  $side=$proposal['side']??null;
  if(!in_array($side,['long','short'],true)||($side==='long'&&$stop>=$entry)||($side==='short'&&$stop<=$entry))
   return ['allowed'=>false,'reason'=>'invalid_stop_or_side'];
  $notional=$entry*$qty;
  if($qty+1e-12<$proposal['min_qty']||$notional+1e-8<$proposal['min_notional'])
   return ['allowed'=>false,'reason'=>'exchange_minimum'];
  if(abs($qty/$proposal['step_size']-round($qty/$proposal['step_size']))>1e-6)
   return ['allowed'=>false,'reason'=>'lot_size'];
  $stopLoss=abs($entry-$stop)*$qty;
  $estimatedLoss=$stopLoss+$proposal['estimated_round_trip_fees']+$proposal['estimated_funding'];
  if($estimatedLoss>$equity*.0025+1e-8)return ['allowed'=>false,'reason'=>'stop_risk_with_costs'];
  if($notional>$equity*.25+1e-8)return ['allowed'=>false,'reason'=>'position_notional'];
  if($notional+$state['gross_notional']>$equity*.5+1e-8)return ['allowed'=>false,'reason'=>'portfolio_exposure'];
  if($notional/$lev>$state['available_margin']-$state['maintenance_margin'])
   return ['allowed'=>false,'reason'=>'insufficient_margin_buffer'];
  if($notional>100&&($proposal['manual_approved']??false)!==true)
   return ['allowed'=>false,'reason'=>'manual_approval_required'];
  return ['allowed'=>true,'reason'=>'hypothetical_preflight_only','estimated_loss'=>$estimatedLoss,
   'initial_margin_estimate'=>$notional/$lev,'notional'=>$notional,
   'liquidation_price'=>null,'execution_enabled'=>false];
 }
}
