<?php
namespace Tests\Feature;

use App\MarketData\CandleImporter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ChartTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_chart_is_read_only_and_execution_disabled(): void
    {
        $this->get('/api/chart')->assertOk()->assertJsonPath('execution_enabled', false)
            ->assertJsonPath('points', [])->assertJsonPath('live', false);
    }

    public function test_chart_uses_completed_candles_and_computed_indicators(): void
    {
        $start = CarbonImmutable::parse('2025-01-01 00:00:00', 'UTC')->getTimestampMs();
        $rows = [];
        for ($i = 0; $i < 510; $i++) {
            $ms = $start + $i * 3600000;
            $close = 100000 + $i * 10;
            $rows[] = [$ms, (string) ($close - 5), (string) ($close + 20),
                (string) ($close - 20), (string) $close, '100',
                $ms + 3599999, '10000', 100, '0', '0', '0'];
        }
        app(CandleImporter::class)->import($rows, CarbonImmutable::createFromTimestampMs($start + 511 * 3600000, 'UTC'));
        $response = $this->get('/api/chart')->assertOk()->assertJsonPath('execution_enabled', false)
            ->assertJsonPath('has_more', true);
        $this->assertCount(240, $response->json('points'));
        $this->assertNotNull($response->json('points.239.ema20'));
        $this->assertNotNull($response->json('points.239.rsi14'));
        $older = $this->get('/api/chart?before='.intdiv($start + 270 * 3600000, 1000))
            ->assertOk()->json('points');
        $this->assertNotEmpty($older);
        $this->assertLessThan($response->json('points.0.time'), end($older)['time']);
        $this->get('/api/chart?before=not-a-date')->assertStatus(422);
    }
}
