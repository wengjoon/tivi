<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class PerformanceServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Enable route caching in production
        if (Config::get('performance.route_cache.enabled') && app()->environment('production')) {
            $this->app['router']->cache();
        }

        // Enable view caching
        if (Config::get('performance.view_cache.enabled')) {
            View::share('view_cache_enabled', true);
        }

        // Enable query caching
        if (Config::get('performance.database.query_cache')) {
            $this->app['db']->enableQueryLog();
        }

        // Register performance middleware
        $this->app['router']->pushMiddlewareToGroup('web', \App\Http\Middleware\OptimizePerformance::class);
    }
} 