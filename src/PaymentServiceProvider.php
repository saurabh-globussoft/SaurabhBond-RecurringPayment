<?php

namespace SaurabhBond\RecurringPayment;

use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
        $this->publishes([__DIR__ . '/views' => base_path('resources/views/saurabh-bond/recurring-payment')]);
        $this->loadViewsFrom(__DIR__ . '/views', 'payment');
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        //
        include __DIR__ . '/routes.php';
        $this->app->make('SaurabhBond\RecurringPayment\PaymentController');
    }
}
