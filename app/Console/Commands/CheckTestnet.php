<?php
namespace App\Console\Commands;

use App\Execution\TestnetAccountProbe;
use Illuminate\Console\Command;
use Throwable;

final class CheckTestnet extends Command
{
    protected $signature = 'astra:check-testnet';
    protected $description = 'Read-only Binance Spot Testnet connectivity diagnostic; never places orders';

    public function handle(TestnetAccountProbe $probe): int
    {
        try {
            $result = $probe->check();
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            if (!$result['connected']) {
                $this->warn('Testnet is not connected. Trading remains disabled.');
                return self::FAILURE;
            }
            $this->info('Testnet account reachable (read-only). Trading remains disabled.');
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Testnet diagnostic failed. Check credentials, DNS, firewall and testnet availability.');
            return self::FAILURE;
        }
    }
}
