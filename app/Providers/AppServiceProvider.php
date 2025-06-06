<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Events\Dispatcher;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.env') === 'production') {
            DB::connection()->setQueryGrammar(new MySqlGrammar());
            DB::connection()->setEventDispatcher(new Dispatcher());
        }

        // Add this line to use Bootstrap pagination styling
        Paginator::useBootstrap();
    }
}
