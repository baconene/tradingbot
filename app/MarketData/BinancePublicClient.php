<?php
namespace App\MarketData;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class BinancePublicClient
{
    /** Production Binance public candles; fail closed on location restrictions. */
    public function candles(string $symbol, string $interval, int $startMs, int $endMs, int $limit = 1000): array
    {
        if ($symbol !== 'BTCUSDT' || $interval !== '1h') {
            throw new RuntimeException('Only approved BTCUSDT 1h data is supported.');
        }
        $response = Http::baseUrl(config('astra.market_data_url'))
            ->acceptJson()->timeout(15)
            ->get('/api/v3/klines', [
                'symbol' => $symbol, 'interval' => $interval,
                'startTime' => $startMs, 'endTime' => $endMs,
                'limit' => min(1000, max(1, $limit)),
            ]);
        if ($response->status() === 451) {
            throw new RuntimeException(
                'Binance HTTP 451: market data is restricted for this server location. '.
                'Stop Binance requests; use an authorized market-data provider and label its venue.'
            );
        }
        if (!$response->successful() || !is_array($response->json())) {
            throw new RuntimeException('Binance market-data request failed (HTTP '.$response->status().').');
        }
        return $response->json();
    }
}
