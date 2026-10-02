<?php
namespace Tests\Unit;

use App\Prediction\FrozenModelValidator;
use App\Prediction\HistoricalProbabilityResearch;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FrozenModelValidatorTest extends TestCase
{
    private function trades(int $count): array
    {
        $rows=[];
        for($i=0;$i<$count;$i++)$rows[]=['pnl'=>$i%3===0?2:-1,
            'entry_context'=>['rsi14'=>$i%2?63:57,'relative_volume'=>$i%4?2.3:1.6]];
        return $rows;
    }
    public function test_frozen_model_evaluates_later_trades_without_retraining(): void
    {
        $model=(new HistoricalProbabilityResearch())->evaluate($this->trades(80));
        $original=$model['training_bucket_counts'];
        $report=(new FrozenModelValidator())->validate($model,$this->trades(45));
        $this->assertSame(45,$report['trade_count']);
        $this->assertSame($original,$model['training_bucket_counts']);
        $this->assertArrayHasKey('beats_frozen_training_prior',$report['criteria']);
        $this->assertArrayHasKey('calibration_mae',$report);
    }
    public function test_small_validation_sample_is_rejected(): void
    {
        $model=(new HistoricalProbabilityResearch())->evaluate($this->trades(80));
        $this->expectException(InvalidArgumentException::class);
        (new FrozenModelValidator())->validate($model,$this->trades(20));
    }
}
