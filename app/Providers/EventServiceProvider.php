<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
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
//        $this->bootEvents();  //: this was auto discovered be default
    }


    private function bootEvents(): void
    {
        Event::subscribe(\App\Listeners\UserEventSubscriber::class);
        Event::subscribe(\App\Listeners\OrderEventSubscriber::class);
        Event::subscribe(\App\Listeners\SaleEventSubscriber::class);
    }
}
