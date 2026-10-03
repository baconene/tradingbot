<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
final class RequireFuturesOperator
{
 public function handle(Request $request,Closure $next): Response
 {
  $expected=config('astra.futures_config_token');
  $provided=$request->bearerToken();
  if(!is_string($expected)||strlen($expected)<32||!is_string($provided)||!hash_equals($expected,$provided))
   return response()->json(['message'=>'Operator authentication required'],403);
  return $next($request);
 }
}
