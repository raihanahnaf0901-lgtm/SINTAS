<?php

namespace App\Providers;

use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

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
        if ($this->app->runningInConsole()) {
            // PHP resolves its upload directory before Laravel boots in the server process.
            ServeCommand::$passthroughVariables = array_values(array_unique([
                ...ServeCommand::$passthroughVariables, 'TEMP', 'TMP', 'TMPDIR',
            ]));
        }

        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }
        Vite::prefetch(concurrency: 3);
    }
}
