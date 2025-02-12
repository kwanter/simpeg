<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Events\Dispatcher;

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
    }
}
