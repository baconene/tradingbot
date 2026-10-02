<?php
namespace App\MarketData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
final class CandleImporter {
    /** @return array{inserted:int,duplicates:int,skipped:int,gaps:int} */
    public function import(array $rows, CarbonImmutable $observedAt): array {
        $stats=['inserted'=>0,'duplicates'=>0,'skipped'=>0,'gaps'=>0];
        $delay=(int)config('astra.market_data_delay_seconds');
        $previous=null;
        foreach ($rows as $row) {
            if (!is_array($row) || count($row)<9) throw new InvalidArgumentException('Malformed Binance kline.');
            [$openMs,$open,$high,$low,$close,$volume,$closeMs,$quoteVolume,$trades]=$row;
            if (!is_numeric($openMs) || !is_numeric($closeMs) || (int)$closeMs !== (int)$openMs+3599999) throw new InvalidArgumentException('Invalid hourly candle timestamps.');
            foreach ([$open,$high,$low,$close,$volume,$quoteVolume] as $v) if (!is_numeric($v) || !is_finite((float)$v) || (float)$v<0) throw new InvalidArgumentException('Invalid candle number.');
            if ((float)$low>min((float)$open,(float)$close) || (float)$high<max((float)$open,(float)$close) || (float)$high<(float)$low || (float)$open<=0 || (float)$close<=0) throw new InvalidArgumentException('Invalid OHLC range.');
            if ((int)$openMs % 3600000 !== 0 || (int)$trades<0) throw new InvalidArgumentException('Invalid kline alignment or trade count.');
            if ($previous!==null && (int)$openMs<=$previous) throw new InvalidArgumentException('Candles must be strictly increasing.');
            if ($previous!==null && (int)$openMs-$previous!==3600000) $stats['gaps']++;
            $previous=(int)$openMs;
            $availableAt=CarbonImmutable::createFromTimestampMs((int)$closeMs+1+$delay*1000,'UTC');
            if ($availableAt->greaterThan($observedAt)) { $stats['skipped']++; continue; }
            $record=['symbol'=>'BTCUSDT','interval'=>'1h',
                'open_time'=>CarbonImmutable::createFromTimestampMs((int)$openMs,'UTC'),
                'close_time'=>CarbonImmutable::createFromTimestampMs((int)$closeMs,'UTC'),
                'open'=>$open,'high'=>$high,'low'=>$low,'close'=>$close,'volume'=>$volume,
                'quote_volume'=>$quoteVolume,'trade_count'=>$trades,'available_at'=>$availableAt,
                'source'=>'binance_spot','created_at'=>now(),'updated_at'=>now()];
            // Immutable candle records: never overwrite historical observations silently.
            $inserted=DB::table('market_candles')->insertOrIgnore($record);
            $stats[$inserted ? 'inserted' : 'duplicates']++;
        }
        return $stats;
    }
}
