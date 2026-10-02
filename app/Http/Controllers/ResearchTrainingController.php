<?php
namespace App\Http\Controllers;

use App\Jobs\RunResearchTraining;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class ResearchTrainingController
{
    public function index(): JsonResponse
    {
        $runs=DB::table('backtest_runs')
            ->whereIn('strategy_version',['MBR-001-optimization-research','MBR-001-walk-forward-research'])
            ->orderByDesc('id')->limit(20)->get()
            ->map(fn($r)=>['id'=>$r->id,'kind'=>$r->strategy_version,'created_at'=>$r->created_at,
                'data_start'=>$r->data_start,'data_end'=>$r->data_end,
                'results'=>json_decode($r->results,true)])->all();
        return response()->json(['mode'=>'research_only','execution_enabled'=>false,
            'training'=>Cache::get('astra:research:training:status',['state'=>'idle']),
            'runs'=>$runs])->header('Cache-Control','no-store');
    }

    public function store(Request $request): JsonResponse
    {
        $expected=(string)config('astra.research_training_token','');
        $provided=(string)$request->bearerToken();
        if($expected==='' || $provided==='' || !hash_equals($expected,$provided))
            return response()->json(['message'=>'Operator training token required'],401);
        if(config('queue.default')==='sync')
            return response()->json(['message'=>'Configure a database or Redis queue worker before retraining'],503);
        if(!Cache::add('astra:research:training:lock',true,3600))
            return response()->json(['message'=>'Research training already queued or running'],409);
        try {
            Cache::put('astra:research:training:status',['state'=>'queued','started_at'=>now()->toIso8601String()],3600);
            RunResearchTraining::dispatch()->onQueue('research');
        } catch(\Throwable $e) {
            Cache::forget('astra:research:training:lock');
            Cache::put('astra:research:training:status',['state'=>'dispatch_failed'],300);
            report($e);
            return response()->json(['message'=>'Could not queue research training'],503);
        }
        return response()->json(['state'=>'queued','execution_enabled'=>false],202)
            ->header('Cache-Control','no-store');
    }
}
