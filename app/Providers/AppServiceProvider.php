<?php

namespace App\Providers;

use App\Listeners\UserEventSubscriber;
use App\Managers\CartManager;
use App\Models\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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

        $this->app->singleton('currentStore', function ($app) {
            $user = Auth::user();

            $store = $user?->store;

            $canSwitchStore = Gate::allows('switch', Store::class);

            if ($canSwitchStore) {
                $xStore = request()->header('X-Store');
                if (! blank($xStore)) {
                    $store = Store::find($xStore);
                }
            }

            return $store;
        });
        $this->bootHelpers();

    }

    private function bootHelpers(): void
    {
        $helpers = __DIR__.'/../Helpers/helpers.php';
        if (file_exists($helpers)) {
            require_once $helpers;
        }
    }
}
