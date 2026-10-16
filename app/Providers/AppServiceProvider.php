<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Pagination\Paginator;
class AppServiceProvider extends ServiceProvider {
 public function register(): void {}
 public function boot(): void {
  Paginator::defaultView('pagination');
  View::composer('layout',function($view){$u=auth()->user();$view->with('activeUser',$u);});
 }
}
