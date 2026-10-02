<?php
namespace App\Http\Controllers;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
final class MarketDataController {
    public function __invoke(): JsonResponse {
        $latest=DB::table('market_candles')->where('symbol','BTCUSDT')->where('interval','1h')->orderByDesc('open_time')->first();
        $snapshot=$latest ? DB::table('market_snapshots')->where('candle_id',$latest->id)->first() : null;
        $lastAvailable=$latest ? CarbonImmutable::parse($latest->available_at,'UTC') : null;
        return response()->json([
            'symbol'=>'BTCUSDT','timeframe'=>'1h','source'=>'binance_spot_public',
            'candle_count'=>DB::table('market_candles')->where('symbol','BTCUSDT')->where('interval','1h')->count(),
            'latest_candle_at'=>$latest?CarbonImmutable::parse($latest->close_time,'UTC')->toIso8601String():null,
            'latest_close'=>$latest?(float)$latest->close:null,
            'fresh'=>$lastAvailable ? $lastAvailable->diffInSeconds(now('UTC'))<=config('astra.max_candle_age_seconds') : false,
            'snapshot'=>$snapshot ? ['features'=>json_decode($snapshot->features,true),
                'data_quality'=>json_decode($snapshot->data_quality,true),
                'feature_version'=>$snapshot->feature_version,'input_hash'=>$snapshot->input_hash] : null,
            'execution_enabled'=>false,
        ]);
    }
}
