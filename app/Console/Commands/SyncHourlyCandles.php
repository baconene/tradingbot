<?php
namespace App\Console\Commands;
use App\MarketData\BinancePublicClient;
use App\MarketData\CandleImporter;
use App\MarketData\SnapshotBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;
final class SyncHourlyCandles extends Command {
    protected $signature='astra:sync-candles {--start= : UTC date YYYY-MM-DD for initial backfill} {--end= : Optional UTC exclusive end date} {--max-pages=0 : Limit requests; 0 means all available}';
    protected $description='Import completed production Binance BTCUSDT hourly candles; never places orders';
    public function handle(BinancePublicClient $client,CandleImporter $importer,SnapshotBuilder $snapshots): int {
        try {
            $now=CarbonImmutable::now('UTC');
            $start=$this->option('start') ? CarbonImmutable::parse($this->option('start'),'UTC') :
                (($last=DB::table('market_candles')->max('open_time')) ? CarbonImmutable::parse($last,'UTC') : $now->subYears(2));
            $end=$this->option('end') ? CarbonImmutable::parse($this->option('end'),'UTC') : $now;
            if ($start->greaterThanOrEqualTo($end) || $end->greaterThan($now->addDay())) throw new \InvalidArgumentException('Invalid sync window.');
            $cursor=$start->getTimestampMs();$endMs=min($end->getTimestampMs()-1,$now->getTimestampMs());
            $pages=0;$totals=['inserted'=>0,'duplicates'=>0,'skipped'=>0,'gaps'=>0];
            while($cursor<=$endMs) {
                $rows=$client->candles('BTCUSDT','1h',$cursor,$endMs);
                if (!$rows) break;
                $stats=$importer->import($rows,$now);
                foreach($totals as $key=>$_) $totals[$key]+=$stats[$key];
                $next=(int)end($rows)[0]+3600000;
                if ($next<=$cursor) throw new \RuntimeException('Non-advancing market-data cursor.');
                $cursor=$next;$pages++;
                if ((int)$this->option('max-pages')>0 && $pages>=(int)$this->option('max-pages')) break;
                if (count($rows)<1000) break;
                usleep(150000); // Polite pacing; HTTP 429/5xx still fail closed.
            }
            $snapshot=$snapshots->latest($now);
            $this->info('Import: '.json_encode($totals).'; snapshot: '.($snapshot?'ready':'insufficient or gapped history'));
            if ($totals['gaps']>0) $this->warn('Gaps detected. Snapshots will abstain until 200+ continuous candles exist.');
            return self::SUCCESS;
        } catch(Throwable $e) { $this->error('Market-data sync failed: '.$e->getMessage());return self::FAILURE; }
    }
}
