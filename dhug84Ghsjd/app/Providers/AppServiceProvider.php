<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\LineRichMenuService;
use App\Services\LineFriendService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
        $this->app->singleton(LineRichMenuService::class, function ($app) {
            return new LineRichMenuService();
        });
        $this->app->singleton(LineFriendService::class, function ($app) {
            return new LineFriendService();
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
