<?php
namespace Tests\Feature;
use App\MarketData\BinancePublicClient;
use App\MarketData\CandleImporter;
use App\MarketData\SnapshotBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
final class MarketDataTest extends TestCase {
    use RefreshDatabase;
    private function rows(int $count,int $startMs=0): array {
        $rows=[];for($i=0;$i<$count;$i++) {
            $ms=$startMs+$i*3600000;$close=100+$i*.1;
            $rows[]=[$ms,(string)$close,(string)($close+2),(string)($close-2),(string)$close,'100',
                $ms+3599999,'10000',100,'0','0','0'];
        }return $rows;
    }
    public function test_import_skips_unfinished_candle_and_is_idempotent(): void {
        $rows=$this->rows(2);$asOf=CarbonImmutable::createFromTimestampMs(3600000+10000,'UTC');
        $importer=app(CandleImporter::class);$first=$importer->import($rows,$asOf);
        $this->assertSame(1,$first['inserted']);$this->assertSame(1,$first['skipped']);
        $again=$importer->import($rows,$asOf);$this->assertSame(1,$again['duplicates']);
        $this->assertDatabaseCount('market_candles',1);
    }
    public function test_public_api_uses_no_credentials_and_never_executes(): void {
        Http::fake(['*/api/v3/klines*'=>Http::response($this->rows(1))]);
        $rows=app(BinancePublicClient::class)->candles('BTCUSDT','1h',0,3600000);
        $this->assertCount(1,$rows);
        Http::assertSent(fn($request)=>!$request->hasHeader('X-MBX-APIKEY') && !str_contains($request->url(),'testnet'));
        $this->get('/api/status')->assertJsonPath('execution_enabled',false);
    }
    public function test_snapshot_only_uses_available_continuous_history(): void {
        $rows=$this->rows(220);$asOf=CarbonImmutable::createFromTimestampMs(220*3600000+10000,'UTC');
        app(CandleImporter::class)->import($rows,$asOf);
        $result=app(SnapshotBuilder::class)->latest($asOf);
        $this->assertNotNull($result);$this->assertSame(220,$result['data_quality']['warmup_candles']);
        $this->assertDatabaseCount('market_snapshots',1);
        $this->assertSame($result['input_hash'],app(SnapshotBuilder::class)->latest($asOf)['input_hash']);
        $this->get('/api/market-data')->assertOk()->assertJsonPath('execution_enabled',false)->assertJsonPath('candle_count',220);
    }
    public function test_gap_prevents_snapshot(): void {
        $rows=$this->rows(220);array_splice($rows,100,1);
        $asOf=CarbonImmutable::createFromTimestampMs(220*3600000+10000,'UTC');
        $stats=app(CandleImporter::class)->import($rows,$asOf);
        $this->assertSame(1,$stats['gaps']);
        $this->assertNull(app(SnapshotBuilder::class)->latest($asOf));
    }
}
