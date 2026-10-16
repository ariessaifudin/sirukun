<?php
namespace App\Http\Middleware;
class RoleMiddleware {
 public function handle($request,\Closure $next,...$roles) {
  abort_unless($request->user() && $request->user()->aktif && in_array($request->user()->roleCode(),$roles,true),403,'Anda tidak memiliki hak untuk tindakan ini.');
  return $next($request);
 }
}
