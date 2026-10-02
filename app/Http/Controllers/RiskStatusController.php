<?php
namespace App\Http\Controllers;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
final class RiskStatusController {
    public function __invoke(): JsonResponse {
        $state=DB::table('risk_state')->first();
        return response()->json(['execution_enabled'=>false,'kill_latched'=>$state?(bool)$state->kill_latched:true,
            'reconciled'=>$state?(bool)$state->reconciled:false,'testnet_submission_certified'=>false,
            'risk_limits'=>['risk_per_trade_pct'=>0.5,'daily_loss_limit_pct'=>2.0,
                'drawdown_kill_pct'=>10.0,'max_positions'=>2,'max_single_notional_pct'=>25.0,
                'max_exposure_pct'=>50.0,'manual_approval_above_usdt'=>100.0]]);
    }
}
