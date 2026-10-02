<?php
namespace Tests\Feature;

use App\Execution\TestnetAccountProbe;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class TestnetProbeTest extends TestCase
{
    public function test_missing_credentials_make_no_requests(): void
    {
        config()->set('astra.testnet_api_key', '');
        config()->set('astra.testnet_api_secret', '');
        Http::fake();
        $this->assertSame('credentials_missing', app(TestnetAccountProbe::class)->check()['reason']);
        Http::assertNothingSent();
    }

    public function test_read_only_signed_account_probe(): void
    {
        config()->set('astra.testnet_url', 'https://testnet.binance.vision');
        config()->set('astra.testnet_api_key', 'test-key');
        config()->set('astra.testnet_api_secret', 'test-secret');
        Http::fake([
            '*testnet.binance.vision/api/v3/time' => Http::response(['serverTime' => 1720000000000]),
            '*testnet.binance.vision/api/v3/account*' => Http::response([
                'canTrade' => true, 'balances' => [['asset' => 'USDT', 'free' => '1000', 'locked' => '0']],
            ]),
        ]);
        $result = app(TestnetAccountProbe::class)->check();
        $this->assertTrue($result['connected']);
        $this->assertSame('authenticated_read_only', $result['reason']);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/v3/account?')
            && $request->hasHeader('X-MBX-APIKEY', 'test-key')
            && str_contains($request->url(), 'signature='));
        Http::assertNotSent(fn ($request) => in_array($request->method(), ['POST', 'PUT', 'DELETE'], true));
    }
}
