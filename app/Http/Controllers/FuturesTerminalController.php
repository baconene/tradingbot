<?php
namespace App\Http\Controllers;

use App\Futures\StrategyConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class FuturesTerminalController
{
 public function __invoke(): JsonResponse
 {
  $configs=DB::table('futures_strategy_configs')->orderByDesc('id')->limit(15)->get()
   ->map(fn($r)=>['id'=>$r->id,'version'=>$r->version,'name'=>$r->name,'status'=>$r->status,
    'parameters'=>json_decode($r->parameters,true),'created_at'=>$r->created_at]);
  $last=DB::table('futures_market_observations')->where('symbol','BTCUSDT')->orderByDesc('open_time')->first();
  $risk=DB::table('futures_risk_snapshots')->where('environment','testnet')->orderByDesc('observed_at')->first();
  return response()->json(['mode'=>'research_only','execution_enabled'=>false,
   'data_source'=>$last?->source,'latest_futures_candle'=>$last?->open_time,
   'risk'=>['reconciled'=>(bool)($risk?->reconciled??false),'kill_latched'=>(bool)($risk?->kill_latched??true),
    'last_observed_at'=>$risk?->observed_at],
   'defaults'=>StrategyConfiguration::DEFAULTS,'configs'=>$configs,
   'readiness'=>['futures_data'=>$last!==null,'exchange_execution'=>false,'protective_orders'=>false,
    'independent_model_validation'=>false,'testnet_certified'=>false]])
   ->header('Cache-Control','no-store');
 }
 public function create(Request $request,StrategyConfiguration $configuration): JsonResponse
 {
  $data=$request->validate(['name'=>'required|string|min:2|max:100','parameters'=>'required|array',
   'parameters.structure_lookback'=>'required|integer|between:5,100',
   'parameters.reward_risk'=>'required|numeric|between:0.5,10',
   'parameters.leverage'=>'required|integer|between:1,100',
   'parameters.risk_per_trade_pct'=>'required|numeric|gt:0|lte:1',
   'parameters.daily_loss_limit_pct'=>'required|numeric|gt:0|lte:5',
   'parameters.drawdown_limit_pct'=>'required|numeric|gt:0|lte:20',
   'parameters.max_position_notional_pct'=>'required|numeric|gt:0|lte:50',
   'parameters.min_model_probability'=>'required|numeric|between:0.5,0.95',
   'parameters.require_regime_confirmation'=>'required|boolean','parameters.allow_short'=>'required|boolean']);
  try{$params=$configuration->normalize($data['parameters']);}
  catch(InvalidArgumentException $e){return response()->json(['message'=>$e->getMessage()],422);}
  $id=DB::table('futures_strategy_configs')->insertGetId(['version'=>(string)Str::uuid(),
   'name'=>$data['name'],'status'=>'draft','parameters'=>json_encode($params,JSON_THROW_ON_ERROR),
   'parameters_hash'=>$configuration->hash($params),'created_at'=>now(),'updated_at'=>now()]);
  return response()->json(['id'=>$id,'status'=>'draft','execution_enabled'=>false],201)
   ->header('Cache-Control','no-store');
 }
}
