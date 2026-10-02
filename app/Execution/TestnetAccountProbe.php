<?php
namespace App\Execution;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Signed GET-only Spot Testnet probe. Never submits or cancels orders. */
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
            throw new RuntimeException('Unapproved Testnet host');
        }
        try {
            $clock = Http::acceptJson()->timeout(10)->get($base.'/api/v3/time');
            if (!$clock->successful()) {
                return ['connected' => false, 'reason' => $clock->status() === 451
                    ? 'region_restricted' : 'time_endpoint_http_error', 'http_status' => $clock->status()];
            }
            if (!is_numeric($clock->json('serverTime'))) {
                return ['connected' => false, 'reason' => 'invalid_server_time'];
            }
            $params = http_build_query([
                'timestamp' => (int) $clock->json('serverTime'),
                'recvWindow' => 5000,
            ], '', '&', PHP_QUERY_RFC3986);
            $signature = hash_hmac('sha256', $params, $secret);
            $response = Http::acceptJson()->withHeaders(['X-MBX-APIKEY' => $key])
                ->timeout(10)->get($base.'/api/v3/account?'.$params.'&signature='.$signature);
            if (!$response->successful()) {
                $code = $response->json('code');
                $reason = match (true) {
                    $response->status() === 451 => 'region_restricted',
                    $code === -1021 => 'clock_or_timestamp_error',
                    $code === -2014 || $code === -2015 || $code === -1022 => 'invalid_credentials_or_signature',
                    default => 'account_request_rejected',
                };
                return ['connected' => false, 'reason' => $reason, 'http_status' => $response->status()];
            }
            return [
                'connected' => true, 'reason' => 'authenticated_read_only',
                'can_trade' => (bool) $response->json('canTrade'),
                'balances' => collect($response->json('balances', []))
                    ->filter(fn ($b) => in_array($b['asset'] ?? '', ['BTC', 'USDT'], true))
                    ->map(fn ($b) => ['asset' => $b['asset'], 'free' => $b['free'], 'locked' => $b['locked']])
                    ->values()->all(),
            ];
        } catch (ConnectionException $e) {
            return ['connected' => false, 'reason' => 'connection_or_dns_failure'];
        }
    }
}
