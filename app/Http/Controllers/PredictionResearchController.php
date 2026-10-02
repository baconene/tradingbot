<?php
namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class PredictionResearchController
{
    public function __invoke(): JsonResponse
    {
        $runs=DB::table('model_versions')->where('version','like','M5-binned-v1-%')
            ->orderByDesc('id')->limit(12)->get()->map(fn($r)=>[
                'id'=>$r->id,'version'=>$r->version,'status'=>$r->status,
                'created_at'=>$r->created_at,'approved'=>$r->status==='paper_approved',
                'metrics'=>json_decode($r->metrics??'{}',true)
            ]);
        return response()->json(['mode'=>'offline_observation','execution_enabled'=>false,
            'model_approval_enabled'=>false,'runs'=>$runs])->header('Cache-Control','no-store');
    }
}
