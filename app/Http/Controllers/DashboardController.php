<?php
namespace App\Http\Controllers;
use Inertia\Inertia;
use Inertia\Response;
use App\Support\TradingPolicy;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonImmutable;
final class DashboardController {
    public function __invoke(TradingPolicy $policy): Response {
        $latest=DB::table('market_candles')->where('symbol','BTCUSDT')->orderByDesc('open_time')->first();
        $snapshot=$latest ? DB::table('market_snapshots')->where('candle_id',$latest->id)->first() : null;
        return Inertia::render('Dashboard', [
            'project'=>['name'=>'GPT Astra','mode'=>'paper','symbol'=>'BTCUSDT','timeframe'=>'1h','capital'=>1000,'currency'=>'USDT'],
            'risk'=>$policy->summary(),
            'services'=>['market_data'=>$latest?'public_feed':'not_connected','prediction'=>'not_implemented','exchange'=>'not_connected'],
            'market'=>['candleCount'=>DB::table('market_candles')->where('symbol','BTCUSDT')->count(),
                'latestClose'=>$latest?(float)$latest->close:null,
                'latestCandle'=>$latest?CarbonImmutable::parse($latest->close_time,'UTC')->toIso8601String():null,
                'fresh'=>$latest?CarbonImmutable::parse($latest->available_at,'UTC')->diffInSeconds(now('UTC'))<=config('astra.max_candle_age_seconds'):false,
                'snapshot'=>$snapshot?json_decode($snapshot->features,true):null],
        ]);
    }
}
