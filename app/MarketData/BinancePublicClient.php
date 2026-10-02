<?php
namespace App\MarketData;
use Illuminate\Support\Facades\Http;
use RuntimeException;
final class BinancePublicClient {
    /** Public production-market candles, never testnet order-book data. No credentials. */
    public function candles(string $symbol, string $interval, int $startMs, int $endMs, int $limit = 1000): array {
        if ($symbol !== 'BTCUSDT' || $interval !== '1h') throw new RuntimeException('Only approved BTCUSDT 1h data is supported.');
        $response = Http::baseUrl(config('astra.market_data_url'))
            ->acceptJson()->timeout(15)->retry(3, 1000)
            ->get('/api/v3/klines', ['symbol'=>$symbol, 'interval'=>$interval, 'startTime'=>$startMs,
                'endTime'=>$endMs, 'limit'=>min(1000, max(1,$limit))]);
        if (!$response->successful() || !is_array($response->json())) throw new RuntimeException('Binance market-data request failed.');
        return $response->json();
    }
}
