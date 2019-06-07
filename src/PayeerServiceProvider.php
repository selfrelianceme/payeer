<?php

namespace Selfreliance\Payeer;
use Illuminate\Support\ServiceProvider;

class PayeerServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
        include __DIR__ . '/routes.php';
        $this->app->make('Selfreliance\Payeer\Payeer');

        $this->publishes([
            __DIR__.'/config/payeer.php' => config_path('payeer.php'),
        ], 'config');
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/config/payeer.php', 'payeer'
        );

        $this->app->bind(Payeer::class, function () {
            return new Payeer();
        });

        $this->app->alias(Payeer::class, 'payment.payeer');
    }
}