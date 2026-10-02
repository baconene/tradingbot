<?php
namespace App\Execution;
use DomainException;
final class OrderStateMachine {
    private const TRANSITIONS=[
        'intent'=>['approved','rejected'], 'approved'=>['submitting','cancelled'],
        'submitting'=>['acknowledged','uncertain','rejected'],
        'uncertain'=>['acknowledged','rejected','cancelled'],
        'acknowledged'=>['partially_filled','filled','cancelled','rejected'],
        'partially_filled'=>['partially_filled','filled','cancelled'],
        'filled'=>[], 'cancelled'=>[], 'rejected'=>[],
    ];
    public function transition(string $from,string $to): string {
        if (!in_array($to,self::TRANSITIONS[$from]??[],true)) throw new DomainException("Invalid order transition $from to $to");
        return $to;
    }
    public function retryAllowed(string $state): bool {return $state==='approved';} // Uncertain requires broker reconciliation first.
}
