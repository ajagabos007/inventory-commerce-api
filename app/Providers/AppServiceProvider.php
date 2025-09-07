<?php

namespace App\Providers;

use App\Listeners\UserEventSubscriber;
use App\Managers\CartManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {

        $this->app->singleton('cart', function ($app) {
            $driver = config('cart.driver', 'both');
            $sessionKey = config('cart.session_key', 'shopping_cart');

            return new CartManager(
                session: $app['session'],
                driver: $driver,
                sessionKey: $sessionKey
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
        Event::subscribe(UserEventSubscriber::class);
    }
}
