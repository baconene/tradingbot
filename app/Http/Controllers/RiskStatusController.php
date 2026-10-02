<?php
namespace App\Http\Controllers;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
final class RiskStatusController {
    public function __invoke(): JsonResponse {
        $state=DB::table('risk_state')->first();
        return response()->json(['execution_enabled'=>false,'kill_latched'=>$state?(bool)$state->kill_latched:true,
            'reconciled'=>$state?(bool)$state->reconciled:false,'testnet_submission_certified'=>false]);
    }
}
