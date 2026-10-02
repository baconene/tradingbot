<?php
namespace App\Execution;
use DomainException;
/** Milestone 4 adapter boundary: no submit method until independent integration certification. */
final class BinanceSpotTestnet {
    public function submit(array $order): never {throw new DomainException('Binance order submission locked pending integration certification.');}
    public function cancel(string $clientOrderId): never {throw new DomainException('Binance cancellation unavailable until integration certification.');}
}
