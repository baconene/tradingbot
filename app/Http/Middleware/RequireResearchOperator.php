<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
final class RequireResearchOperator {
 public function handle(Request $request,Closure $next): Response {
  $expected=config('astra.operator_token');
  $supplied=$request->bearerToken();
  if(!is_string($expected)||strlen($expected)<32||!is_string($supplied)||!hash_equals($expected,$supplied)){
   return response()->json(['message'=>'Research operator authorization is not configured or is invalid.'],403);
  }
  return $next($request);
 }
}