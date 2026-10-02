<?php
namespace Tests\Unit;

use App\Prediction\HistoricalProbabilityResearch;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class HistoricalProbabilityResearchTest extends TestCase
{
    private function trades(int $count): array
    {
        $rows=[];
        for($i=0;$i<$count;$i++)$rows[]=['entry_index'=>$i,'pnl'=>$i%3===0?2.0:-1.0,
            'entry_context'=>['rsi14'=>$i%2?63:57,'relative_volume'=>$i%4?1.6:2.2]];
        return $rows;
    }
    public function test_holdout_is_chronological_and_model_is_never_approved(): void
    {
        $report=(new HistoricalProbabilityResearch())->evaluate($this->trades(80));
        $this->assertSame(60,$report['training_trades']);
        $this->assertSame(20,$report['holdout_trades']);
        $this->assertFalse($report['approved']);
        $this->assertSame('observation',$report['status']);
        $this->assertSame(60,$report['holdout_predictions'][0]['entry_index']);
        $this->assertGreaterThanOrEqual(0,$report['brier_score']);
        $this->assertLessThanOrEqual(1,$report['brier_score']);
    }
    public function test_insufficient_data_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new HistoricalProbabilityResearch())->evaluate($this->trades(20));
    }
    public function test_unavailable_indicators_are_not_fabricated(): void
    {
        $rows=$this->trades(60);$rows[0]['entry_context']=[];
        $this->expectException(InvalidArgumentException::class);
        (new HistoricalProbabilityResearch())->evaluate($rows);
    }
}
