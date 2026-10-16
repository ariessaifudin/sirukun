<?php
namespace App\Http\Middleware;
class SecurityHeaders {
 public function handle($request,\Closure $next) {
  if(auth()->check() && !auth()->user()->aktif){auth()->logout();$request->session()->invalidate();$request->session()->regenerateToken();return redirect()->route('login');}
  $r=$next($request);$r->headers->set('X-Content-Type-Options','nosniff');$r->headers->set('X-Frame-Options','SAMEORIGIN');$r->headers->set('Referrer-Policy','same-origin');
  $r->headers->set('Cache-Control','no-store, private');return $r;
 }
}
