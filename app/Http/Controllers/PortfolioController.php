<?php
namespace App\Http\Controllers;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
final class PortfolioController {
    public function __invoke(): JsonResponse {
        return response()->json(['mode'=>'shadow_research','starting_capital_usdt'=>1000,
            'latest'=>DB::table('portfolio_snapshots')->where('portfolio','shadow')->orderByDesc('observed_at')->first(),
            'execution_enabled'=>false]);
    }
}
