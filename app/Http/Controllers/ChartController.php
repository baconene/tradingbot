<?php
namespace App\Http\Controllers;

use App\MarketData\HourlyFeatures;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class ChartController
{
    /** Completed candles only. Read-only, capped response for a one-hour research chart. */
    public function __invoke(HourlyFeatures $features): JsonResponse
    {
        $rows = DB::table('market_candles')->where('symbol', 'BTCUSDT')->where('interval', '1h')
            ->orderByDesc('open_time')->limit(500)->get()->reverse()->values();
        $all = $rows->map(fn ($r) => [
            'open_ms' => CarbonImmutable::parse($r->open_time, 'UTC')->getTimestampMs(),
            'open' => (float) $r->open, 'high' => (float) $r->high,
            'low' => (float) $r->low, 'close' => (float) $r->close,
            'volume' => (float) $r->volume,
        ])->all();
        $points = [];
        foreach ($all as $i => $bar) {
            if ($i < 199 || $i < count($all) - 240) continue;
            $window = array_slice($all, max(0, $i - 249), min(250, $i + 1));
            try {
                $f = $features->calculate($window);
            } catch (\InvalidArgumentException $e) {
                // A historical gap must not create misleading indicator lines.
                $f = null;
            }
            $points[] = [
                'time' => gmdate('Y-m-d\TH:i:s\Z', intdiv($bar['open_ms'], 1000)),
                'open' => $bar['open'], 'high' => $bar['high'], 'low' => $bar['low'],
                'close' => $bar['close'], 'volume' => $bar['volume'],
                'ema20' => $f['ema20'] ?? null, 'ema50' => $f['ema50'] ?? null,
                'rsi14' => $f['rsi14'] ?? null, 'atr14' => $f['atr14'] ?? null,
                'previous20High' => $f['previous_20_high'] ?? null,
                'relativeVolume' => $f['relative_volume'] ?? null,
            ];
        }
        $run = DB::table('backtest_runs')->orderByDesc('id')->first();
        $markers = [];
        if ($run) {
            $results = json_decode($run->results, true);
            $start = CarbonImmutable::parse($run->data_start, 'UTC')->getTimestamp();
            foreach (($results['trades'] ?? []) as $trade) {
                foreach (['entry', 'exit'] as $kind) {
                    $index = $trade[$kind.'_index'] ?? null;
                    if (!is_int($index)) continue;
                    $markers[] = [
                        'time' => gmdate('Y-m-d\TH:i:s\Z', $start + $index * 3600),
                        'kind' => $kind, 'price' => (float) $trade[$kind],
                        'pnl' => (float) $trade['pnl'],
                    ];
                }
            }
        }
        return response()->json([
            'symbol' => 'BTCUSDT', 'interval' => '1h', 'source' => 'binance_spot_public',
            'live' => false, 'refresh_seconds' => 60, 'points' => $points,
            'markers' => $markers, 'backtest_id' => $run?->id,
            'backtest_data_end' => $run?->data_end,
            'latest_candle_at' => count($points) ? end($points)['time'] : null,
            'execution_enabled' => false,
        ]);
    }
}
