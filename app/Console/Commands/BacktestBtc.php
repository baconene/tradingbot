<?php
namespace App\Console\Commands;
use App\Backtesting\HourlyBacktester;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
final class BacktestBtc extends Command {
    protected $signature='astra:backtest {--start=} {--end=}';
    protected $description='Run MBR-001 historical research; does not place orders';
    public function handle(HourlyBacktester $engine): int {
        $query=DB::table('market_candles')->where('symbol','BTCUSDT')->where('interval','1h');
        if ($this->option('start')) $query->where('open_time','>=',$this->option('start'));
        if ($this->option('end')) $query->where('open_time','<',$this->option('end'));
        $rows=$query->orderBy('open_time')->get();
        if ($rows->count()<250) {$this->error('At least 250 continuous completed candles needed');return self::FAILURE;}
        $candles=$rows->map(fn($r)=>['open_ms'=>CarbonImmutable::parse($r->open_time,'UTC')->getTimestampMs(),
            'open'=>(float)$r->open,'high'=>(float)$r->high,'low'=>(float)$r->low,
            'close'=>(float)$r->close,'volume'=>(float)$r->volume])->all();
        try {$report=$engine->run($candles);}catch (\InvalidArgumentException $e) {$this->error($e->getMessage());return self::FAILURE;}
        $this->line(json_encode(array_diff_key($report,array_flip(['trades','equity_curve'])),JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
        DB::table('backtest_runs')->insert(['strategy_version'=>$report['strategy'],'data_start'=>$rows->first()->open_time,
            'data_end'=>$rows->last()->close_time,'data_hash'=>hash('sha256',json_encode($candles,JSON_THROW_ON_ERROR)),
            'results'=>json_encode($report,JSON_THROW_ON_ERROR),'created_at'=>now(),'updated_at'=>now()]);
        return self::SUCCESS;
    }
}
