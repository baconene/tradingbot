<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

final class RunResearchTraining implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout=1800;
    public int $tries=1;

    public function handle(): void
    {
        try {
            foreach(['astra:backtest','astra:optimize','astra:walk-forward'] as $command){
                Cache::put('astra:research:training:status',['state'=>'running','step'=>$command,
                    'started_at'=>now()->toIso8601String()],3600);
                if(Artisan::call($command)!==0)
                    throw new RuntimeException($command.' failed; check candle continuity and worker logs');
            }
            Cache::put('astra:research:training:status',['state'=>'completed',
                'finished_at'=>now()->toIso8601String()],3600);
        } catch(Throwable $e) {
            Cache::put('astra:research:training:status',['state'=>'failed','message'=>$e->getMessage(),
                'finished_at'=>now()->toIso8601String()],3600);
            throw $e;
        } finally {
            Cache::forget('astra:research:training:lock');
        }
    }

    public function failed(?Throwable $e): void
    {
        Cache::put('astra:research:training:status',['state'=>'failed',
            'message'=>'Training worker failed or timed out; inspect Forge worker logs'],3600);
        Cache::forget('astra:research:training:lock');
    }
}
