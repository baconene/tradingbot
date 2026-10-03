<?php
namespace App\Futures;

use InvalidArgumentException;

final class StrategyConfiguration
{
 public const DEFAULTS=[
  'structure_lookback'=>20,'reward_risk'=>2.0,'leverage'=>2,
  'risk_per_trade_pct'=>0.25,'daily_loss_limit_pct'=>2.0,
  'drawdown_limit_pct'=>10.0,'max_position_notional_pct'=>25.0,
  'min_model_probability'=>0.65,'require_regime_confirmation'=>true,
  'allow_short'=>false
 ];
 public function normalize(array $input): array
 {
  $p=array_replace(self::DEFAULTS,$input);
  foreach(['structure_lookback','leverage'] as $key)
   if(!is_int($p[$key]))throw new InvalidArgumentException("$key must be an integer");
  foreach(['reward_risk','risk_per_trade_pct','daily_loss_limit_pct','drawdown_limit_pct','max_position_notional_pct','min_model_probability'] as $key)
   if(!is_numeric($p[$key])||!is_finite((float)$p[$key]))throw new InvalidArgumentException("Invalid $key");
  foreach(['require_regime_confirmation','allow_short'] as $key)
   if(!is_bool($p[$key]))throw new InvalidArgumentException("Invalid $key");
  if($p['structure_lookback']<5||$p['structure_lookback']>100||$p['leverage']<1||$p['leverage']>100||
    $p['reward_risk']<0.5||$p['reward_risk']>10||
    $p['risk_per_trade_pct']<=0||$p['risk_per_trade_pct']>1||
    $p['daily_loss_limit_pct']<=0||$p['daily_loss_limit_pct']>5||
    $p['drawdown_limit_pct']<=0||$p['drawdown_limit_pct']>20||
    $p['max_position_notional_pct']<=0||$p['max_position_notional_pct']>50||
    $p['min_model_probability']<0.5||$p['min_model_probability']>0.95)
    throw new InvalidArgumentException('Strategy configuration outside research safety bounds');
  return $p;
 }
 public function hash(array $parameters): string
 {
  ksort($parameters);
  return hash('sha256',json_encode($parameters,JSON_THROW_ON_ERROR));
 }
}
