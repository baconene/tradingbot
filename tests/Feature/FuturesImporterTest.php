<?php
namespace Tests\Feature;
use App\MarketData\FuturesImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
final class FuturesImporterTest extends TestCase {
 use RefreshDatabase;
 public function test_only_completed_candles_are_saved(): void {
  Http::fake(['*/fapi/v1/klines*'=>Http::response([
   [60000000,'100','101','99','100','5',60059999],
   [60060000,'100','101','99','100','5',60119999],
   [60120000,'100','101','99','100','5',60239999],
  ],200)]);
  $r=app(FuturesImporter::class)->sync('BTCUSDT',1,60200000);
  $this->assertSame(2,$r['imported']);
  $this->assertSame(2,DB::table('futures_candles')->count());
  Http::assertSent(fn($req)=>str_contains($req->url(),'/fapi/v1/klines'));
 }
}