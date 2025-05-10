<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Schema;
use App\Http\Controllers\Api\V1\DocumentGeneratorController;
use App\Http\Controllers\Api\V1\DocumentAnalyzerController;
use App\Http\Controllers\Api\V1\DocumentContentController;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DocumentGeneratorController::class);
        $this->app->singleton(DocumentAnalyzerController::class);
        $this->app->singleton(DocumentContentController::class);
    }

    public function boot(UrlGenerator $url): void
    {
        if (env('APP_ENV') !== 'local') {
            $url->forceScheme('https');
        }
        
        Schema::defaultStringLength(191);
    }
}



