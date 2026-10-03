<?php
namespace App\Http\Middleware;
use Inertia\Middleware;
final class HandleInertiaRequests extends Middleware {
 protected $rootView='app';
 public function share(\Illuminate\Http\Request $request): array {return parent::share($request);}
}