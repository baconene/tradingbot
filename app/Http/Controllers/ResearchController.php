<?php
namespace App\Http\Controllers;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
final class ResearchController {
    public function __invoke(): JsonResponse {
        $latest=DB::table('backtest_runs')->where('strategy_version',\App\Strategies\MomentumBreakout::VERSION)->orderByDesc('id')->first();
        return response()->json(['strategy'=>'MBR-001-v1','execution_enabled'=>false,
            'latest_backtest'=>$latest?['id'=>$latest->id,'data_start'=>$latest->data_start,'data_end'=>$latest->data_end,
                'data_hash'=>$latest->data_hash,'results'=>json_decode($latest->results,true)]:null]);
    }
}
