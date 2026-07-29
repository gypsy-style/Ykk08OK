<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\LineRichMenuService;
use App\Services\LineFriendService;
use App\Services\Line\LineSender;
use App\Services\Line\DirectLineSender;
use App\Services\Line\HarnessLineSender;

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

        // LINEメッセージの送信経路を config で切り替える
        $this->app->bind(LineSender::class, function ($app) {
            if (config('services.line.driver') === 'harness') {
                return new HarnessLineSender();
            }
            return new DirectLineSender();
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
