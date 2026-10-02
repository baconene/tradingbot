<?php
namespace App\Http\Controllers;

use App\Risk\RiskDecision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShadowRiskController
{
    /** Hypothetical, read-only scenario. No credentials, persisted orders or broker calls. */
    public function __invoke(Request $request, RiskDecision $risk): JsonResponse
    {
        $v=$request->validate([
            'equity'=>'required|numeric|gt:0|lte:100000000',
            'daily_reference'=>'required|numeric|gt:0|lte:100000000',
            'high_water'=>'required|numeric|gt:0|lte:100000000',
            'daily_pnl'=>'required|numeric|between:-100000000,100000000',
            'open_positions'=>'required|integer|between:0,100',
            'exposure'=>'required|numeric|between:0,100000000',
            'entry'=>'required|numeric|gt:0',
            'stop'=>'required|numeric|gt:0',
            'quantity'=>'required|numeric|gt:0',
            'min_notional'=>'required|numeric|gt:0',
            'step_size'=>'required|numeric|gt:0',
            'min_qty'=>'required|numeric|gt:0',
            'manual_approved'=>'sometimes|boolean',
        ]);
        // Scenario assumes a reconciled, unlatched HYPOTHETICAL state to expose limits.
        // The real persisted kill switch is never read, reset or overridden here.
        $state=array_intersect_key($v,array_flip(['equity','daily_reference','high_water','daily_pnl','open_positions','exposure']))
            +['kill_latched'=>false,'reconciled'=>true];
        $order=array_intersect_key($v,array_flip(['entry','stop','quantity','min_notional','step_size','min_qty','manual_approved']));
        $decision=$risk->evaluate($state,$order);
        return response()->json(['mode'=>'hypothetical_only','execution_enabled'=>false,
            'assumptions'=>['hypothetical_reconciled'=>true,'hypothetical_kill_latched'=>false,
                'filters'=>'user_supplied_unverified','fees_and_slippage'=>'not_included'],
            'decision'=>$decision])->header('Cache-Control','no-store');
    }
}
