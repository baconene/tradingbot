<?php
namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class BacktestExportController
{
    /** Export persisted research only. Requires a separately configured read-only token. */
    public function __invoke(Request $request): JsonResponse
    {
        $expected=(string)config('astra.research_export_token','');
        $provided=(string)$request->bearerToken();
        if ($expected==='' || $provided==='' || !hash_equals($expected,$provided)) {
            return response()->json(['message'=>'Unauthorized'],401);
        }
        $limit=min(25,max(1,(int)$request->query('limit',5)));
        $runs=DB::table('backtest_runs')->orderByDesc('id')->limit($limit)->get()
            ->map(fn($r)=>[
                'id'=>$r->id,'strategy_version'=>$r->strategy_version,
                'data_start'=>$r->data_start,'data_end'=>$r->data_end,
                'data_hash'=>$r->data_hash,'created_at'=>$r->created_at,
                'results'=>json_decode($r->results,true),
            ]);
        return response()->json([
            'mode'=>'read_only_research','execution_enabled'=>false,
            'count'=>$runs->count(),'runs'=>$runs,
            'caution'=>'In-sample win rate is not proof of future performance. Evaluate untouched out-of-sample periods.',
        ])->header('Cache-Control','no-store');
    }
}
