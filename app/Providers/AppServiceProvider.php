<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(UrlGenerator $url): void
    {
        if (env('APP_ENV') !== 'local') {
            $url->forceScheme('https');
        }
        
        Schema::defaultStringLength(191);
    }
}


