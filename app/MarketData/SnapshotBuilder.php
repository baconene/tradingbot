<?php
namespace App\MarketData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
final class SnapshotBuilder {
    public function __construct(private HourlyFeatures $features) {}
    /** Builds latest eligible snapshot only. Never uses candles published after decision time. */
    public function latest(CarbonImmutable $asOf): ?array {
        $latest=DB::table('market_candles')->where('symbol','BTCUSDT')->where('interval','1h')
            ->where('available_at','<=',$asOf)->orderByDesc('open_time')->first();
        if (!$latest) return null;
        $rows=DB::table('market_candles')->where('symbol','BTCUSDT')->where('interval','1h')
            ->where('open_time','<=',$latest->open_time)->where('available_at','<=',$asOf)
            ->orderByDesc('open_time')->limit(250)->get()->reverse()->values();
        if ($rows->count()<200) return null;
        $input=$rows->map(fn($r)=>['open_ms'=>CarbonImmutable::parse($r->open_time,'UTC')->getTimestampMs(),
            'open'=>(float)$r->open,'high'=>(float)$r->high,'low'=>(float)$r->low,'close'=>(float)$r->close,
            'volume'=>(float)$r->volume])->all();
        try { $features=$this->features->calculate($input); }
        catch (\InvalidArgumentException) { return null; }
        $decisionTime=CarbonImmutable::parse($latest->available_at,'UTC');
        $hash=hash('sha256',json_encode(['version'=>HourlyFeatures::VERSION,'candles'=>$input],JSON_THROW_ON_ERROR));
        $record=['candle_id'=>$latest->id,'symbol'=>'BTCUSDT','interval'=>'1h',
            'decision_time'=>$decisionTime,'feature_version'=>HourlyFeatures::VERSION,
            'features'=>json_encode($features,JSON_THROW_ON_ERROR),'input_hash'=>$hash,
            'data_quality'=>json_encode(['complete'=>true,'stale'=>$decisionTime->diffInSeconds($asOf)>config('astra.max_candle_age_seconds'),
                'continuous'=>true,'warmup_candles'=>count($input),'spread_available'=>false],JSON_THROW_ON_ERROR),
            'created_at'=>now(),'updated_at'=>now()];
        DB::table('market_snapshots')->insertOrIgnore($record);
        return ['candle_id'=>$latest->id,'decision_time'=>$decisionTime->toIso8601String(),
            'features'=>$features,'input_hash'=>$hash,'data_quality'=>json_decode($record['data_quality'],true)];
    }
}
