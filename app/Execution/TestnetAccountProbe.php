<?php
namespace App\Execution;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Read-only Binance Spot Testnet connectivity. Never submits or cancels orders.
 * API keys remain server-side and are never included in diagnostics.
 */
final class TestnetAccountProbe
{
    public function check(): array
    {
        $key = (string) config('astra.testnet_api_key', '');
        $secret = (string) config('astra.testnet_api_secret', '');
        if ($key === '' || $secret === '') {
            return ['connected' => false, 'reason' => 'credentials_missing'];
        }

        $base = rtrim((string) config('astra.testnet_url'), '/');
        if ($base !== 'https://testnet.binance.vision') {
            throw new RuntimeException('Only the approved Binance Spot Testnet host is permitted.');
        }

        $clock = Http::acceptJson()->timeout(10)->get($base.'/api/v3/time');
        if (!$clock->successful() || !is_numeric($clock->json('serverTime'))) {
            throw new RuntimeException('Testnet time endpoint unavailable.');
        }

        $timestamp = (int) $clock->json('serverTime');
        $params = http_build_query(['timestamp' => $timestamp, 'recvWindow' => 5000], '', '&', PHP_QUERY_RFC3986);
        $signature = hash_hmac('sha256', $params, $secret);
        $response = Http::acceptJson()->withHeaders(['X-MBX-APIKEY' => $key])
            ->timeout(10)->get($base.'/api/v3/account?'.$params.'&signature='.$signature);

        if (!$response->successful()) {
            // Do not log response bodies: upstream errors can contain sensitive details.
            return ['connected' => false, 'reason' => 'account_request_rejected', 'http_status' => $response->status()];
        }

        return [
            'connected' => true,
            'reason' => 'authenticated_read_only',
            'can_trade' => (bool) $response->json('canTrade'),
            'balances' => collect($response->json('balances', []))
                ->filter(fn ($b) => in_array($b['asset'] ?? '', ['BTC', 'USDT'], true))
                ->map(fn ($b) => ['asset' => $b['asset'], 'free' => $b['free'], 'locked' => $b['locked']])
                ->values()->all(),
        ];
    }
}
